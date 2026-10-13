<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoicePdf;
use App\Services\Invoicing;
use App\Services\InvoicingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * @tags Invoices
 */
class InvoiceController extends Controller
{
    /**
     * List invoices.
     *
     * Newest first. Filter with `status` (`draft`, `sent`, `paid`, or `overdue`
     * for sent invoices past their due date) and `customer` (id).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'sent', 'paid', 'overdue'])],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
        $status = $validated['status'] ?? null;

        $invoices = Invoice::query()
            ->with('lines')
            ->when($status === 'overdue', fn ($query) => $query->overdue())
            ->when($status && $status !== 'overdue', fn ($query) => $query->where('status', $status))
            ->when($validated['customer'] ?? null, fn ($query, $customer) => $query->where('customer_id', $customer))
            ->orderByDesc('number')
            ->get();

        return InvoiceResource::collection($invoices);
    }

    /**
     * A customer's billable requests.
     *
     * Completed requests with work or materials that aren't on an invoice yet:
     * what a new invoice for the customer can include.
     */
    public function billable(Customer $customer, Invoicing $invoicing): AnonymousResourceCollection
    {
        return ServiceRequestResource::collection($invoicing->billableRequests($customer)->with('items', 'customer')->orderBy('id')->get());
    }

    /**
     * Create an invoice.
     *
     * A draft for the chosen billable requests of one customer. It gets the
     * next invoice number, its reference number, and a due date from the
     * customer's payment terms.
     */
    public function store(Request $request, Invoicing $invoicing): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $invoice = $invoicing->create(Customer::findOrFail($validated['customer_id']), $validated['request_ids'], $validated['notes'] ?? null);
        } catch (InvoicingException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['request_ids' => [$exception->getMessage()]]], 422);
        }

        return (new InvoiceResource($invoice->load('lines')))->response()->setStatusCode(201);
    }

    /**
     * Get an invoice.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('lines'));
    }

    /**
     * Change an invoice's status.
     *
     * Draft → sent → paid; a paid invoice can go back to sent.
     */
    public function updateStatus(Request $request, Invoice $invoice, Invoicing $invoicing): JsonResponse|InvoiceResource
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(InvoiceStatus::class)]]);

        try {
            $invoicing->changeStatus($invoice, InvoiceStatus::from($validated['status']));
        } catch (InvoicingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return new InvoiceResource($invoice->load('lines'));
    }

    /**
     * Delete a draft invoice.
     *
     * Its requests can be invoiced again. Sent and paid invoices are kept.
     */
    public function destroy(Invoice $invoice, Invoicing $invoicing): Response|JsonResponse
    {
        try {
            $invoicing->delete($invoice);
        } catch (InvoicingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->noContent();
    }

    /**
     * Download an invoice as a PDF.
     */
    public function pdf(Invoice $invoice, InvoicePdf $pdf): Response
    {
        return response($pdf->render($invoice->load('lines')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', "lasku-{$invoice->number}.pdf"),
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /**
     * Export invoices for accounting.
     *
     * One row per invoice and VAT rate: number, reference, dates, customer,
     * status and the amounts. CSV (semicolons, for Excel) or JSON. Filter like
     * the list.
     */
    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'format' => ['required', 'in:csv,json'],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'paid', 'overdue'])],
        ]);
        $status = $validated['status'] ?? null;

        $invoices = Invoice::query()
            ->with('lines')
            ->when($status === 'overdue', fn ($query) => $query->overdue())
            ->when($status && $status !== 'overdue', fn ($query) => $query->where('status', $status))
            ->orderBy('number')
            ->get();

        $rows = [];
        foreach ($invoices as $invoice) {
            foreach ($invoice->totalsCents()['by_rate'] as $rate => $sums) {
                $rows[] = [
                    'number' => $invoice->number,
                    'reference_number' => $invoice->reference_number,
                    'issue_date' => $invoice->issue_date->toDateString(),
                    'due_date' => $invoice->due_date->toDateString(),
                    'customer' => $invoice->customer_name,
                    'customer_business_id' => $invoice->customer_business_id,
                    'status' => $invoice->status->value,
                    'vat_rate' => $rate / 100,
                    'net' => $sums['net'] / 100,
                    'vat' => $sums['vat'] / 100,
                    'total' => ($sums['net'] + $sums['vat']) / 100,
                ];
            }
        }

        $filename = 'jalaops-laskut-'.now()->timezone(config('app.display_timezone'))->format('Y-m-d').'.'.$validated['format'];
        $headers = ['Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename), 'Cache-Control' => 'private, no-cache'];

        if ($validated['format'] === 'json') {
            return response(json_encode(['app' => 'JalaOps', 'exported_at' => now()->toIso8601String(), 'invoices' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json'] + $headers);
        }

        // Semicolons, decimal commas and a byte order mark, as Excel in Finnish expects.
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['number', 'reference_number', 'issue_date', 'due_date', 'customer', 'customer_business_id', 'status', 'vat_rate', 'net', 'vat', 'total'], ';', '"', '');
        foreach ($rows as $row) {
            foreach (['vat_rate', 'net', 'vat', 'total'] as $field) {
                $row[$field] = number_format($row[$field], 2, ',', '');
            }
            fputcsv($handle, array_values($row), ';', '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8'] + $headers);
    }
}
