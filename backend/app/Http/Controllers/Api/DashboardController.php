<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Dashboard
 */
class DashboardController extends Controller
{
    /**
     * Request counts.
     *
     * How many requests are open, in progress and completed, how many are
     * overdue (not completed and due before today), and the total; and the
     * unpaid (sent) invoices, their sum and how many are past their due date.
     *
     * @response array{open: int, in_progress: int, completed: int, overdue: int, total: int, invoices: array{unpaid: int, unpaid_total: float, overdue: int}}
     */
    public function show(): JsonResponse
    {
        $byStatus = ServiceRequest::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $counts = collect(RequestStatus::cases())
            ->mapWithKeys(fn (RequestStatus $status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)]);

        // Sent invoices waiting for payment, and those past their due date.
        $unpaid = Invoice::query()->with('lines')->where('status', InvoiceStatus::Sent)->get();

        return response()->json([
            ...$counts,
            'overdue' => ServiceRequest::query()->dueIn('overdue')->count(),
            'total' => $counts->sum(),
            'invoices' => [
                'unpaid' => $unpaid->count(),
                'unpaid_total' => $unpaid->sum(fn (Invoice $invoice) => $invoice->totalsCents()['total']) / 100,
                'overdue' => $unpaid->filter(fn (Invoice $invoice) => $invoice->isOverdue())->count(),
            ],
        ]);
    }
}
