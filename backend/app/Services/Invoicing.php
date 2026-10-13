<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Support\ReferenceNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Makes invoices from completed requests and moves them through their statuses.
 */
class Invoicing
{
    /**
     * A customer's requests that can be invoiced: completed, with work or
     * materials, and not on an invoice yet.
     *
     * @return Builder<ServiceRequest>
     */
    public function billableRequests(Customer $customer): Builder
    {
        return ServiceRequest::query()
            ->where('customer_id', $customer->id)
            ->where('status', RequestStatus::Completed)
            ->whereNull('invoice_id')
            ->has('items');
    }

    /**
     * Creates a draft invoice for the given billable requests of a customer.
     *
     * @param  list<int>  $requestIds
     *
     * @throws InvoicingException when a request can't be invoiced
     */
    public function create(Customer $customer, array $requestIds, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($customer, $requestIds, $notes) {
            $requests = $this->billableRequests($customer)->whereIn('id', $requestIds)->with('items')->orderBy('id')->get();

            if ($requests->count() !== count(array_unique($requestIds))) {
                throw new InvoicingException('Laskulle voi valita vain asiakkaan valmiita pyyntöjä, joilla on töitä tai tarvikkeita ja joita ei ole vielä laskutettu.');
            }

            // The next number in the running sequence; the unique index stops
            // two invoices from ever getting the same one.
            $number = max((int) Invoice::query()->lockForUpdate()->max('number'), config('billing.first_invoice_number') - 1) + 1;
            // The invoice date is the date in Finland, not on the UTC server clock.
            $issueDate = today(config('app.display_timezone'));

            $invoice = Invoice::create([
                'number' => $number,
                'reference_number' => ReferenceNumber::fromBase($number),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_business_id' => $customer->business_id,
                'billing_street_address' => $customer->billing_street_address,
                'billing_postal_code' => $customer->billing_postal_code,
                'billing_city' => $customer->billing_city,
                'issue_date' => $issueDate,
                'due_date' => $issueDate->copy()->addDays($customer->payment_terms_days),
                'notes' => $notes,
            ]);

            foreach ($requests as $request) {
                foreach ($request->items as $item) {
                    $invoice->lines()->create([
                        'service_request_id' => $request->id,
                        'request_title' => $request->title,
                        'delivered_on' => ($request->signed_at ?? $request->updated_at)?->toDateString(),
                        'type' => $item->type,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'unit_price_cents' => $item->unit_price_cents,
                        'vat_rate_basis_points' => $item->vat_rate_basis_points,
                    ]);
                }
                $request->forceFill(['invoice_id' => $invoice->id])->saveQuietly();
            }

            return $invoice;
        });
    }

    /**
     * Moves an invoice to a status: draft → sent → paid, and back from paid to sent.
     *
     * @throws InvoicingException for any other change
     */
    public function changeStatus(Invoice $invoice, InvoiceStatus $status): Invoice
    {
        $allowed = [
            InvoiceStatus::Draft->value => [InvoiceStatus::Sent],
            InvoiceStatus::Sent->value => [InvoiceStatus::Paid],
            InvoiceStatus::Paid->value => [InvoiceStatus::Sent],
        ];

        if (! in_array($status, $allowed[$invoice->status->value], true)) {
            throw new InvoicingException("Laskun tilaa ei voi vaihtaa: {$invoice->status->label()} → {$status->label()}.");
        }

        $invoice->status = $status;
        if ($status === InvoiceStatus::Sent) {
            $invoice->sent_at ??= now();
            $invoice->paid_at = null;
        }
        if ($status === InvoiceStatus::Paid) {
            $invoice->paid_at = now();
        }
        $invoice->save();

        return $invoice;
    }

    /**
     * Deletes a draft invoice; its requests can be invoiced again.
     *
     * @throws InvoicingException when the invoice isn't a draft
     */
    public function delete(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new InvoicingException('Vain luonnoksen voi poistaa. Lähetetty lasku säilytetään kirjanpitoa varten.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->serviceRequests()->update(['invoice_id' => null]);
            $invoice->delete();
        });
    }
}
