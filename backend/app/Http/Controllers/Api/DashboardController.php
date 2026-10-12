<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
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
     * overdue (not completed and due before today), and the total.
     *
     * @response array{open: int, in_progress: int, completed: int, overdue: int, total: int}
     */
    public function show(): JsonResponse
    {
        $byStatus = ServiceRequest::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $counts = collect(RequestStatus::cases())
            ->mapWithKeys(fn (RequestStatus $status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)]);

        return response()->json([
            ...$counts,
            'overdue' => ServiceRequest::query()->dueIn('overdue')->count(),
            'total' => $counts->sum(),
        ]);
    }
}
