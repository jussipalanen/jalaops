<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The backend's home page: a small overview of the API with links to its
 * documentation. API clients asking for JSON get the same links as JSON.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'name' => config('app.name'),
                'api' => url('/api'),
                'docs' => url('/docs'),
            ]);
        }

        return view('home', [
            'version' => config('scramble.info.version'),
            'demo' => config('app.demo_mode'),
            'frontendUrl' => config('app.frontend_url'),
            'statusCounts' => $this->statusCounts(),
        ]);
    }

    /**
     * Number of requests per status, or null when the database can't be reached.
     *
     * @return array<string, int>|null
     */
    private function statusCounts(): ?array
    {
        try {
            $counts = ServiceRequest::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');
        } catch (Throwable) {
            return null;
        }

        return collect(RequestStatus::cases())
            ->mapWithKeys(fn (RequestStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }
}
