<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequestsRequest;
use App\Services\ImportException;
use App\Services\RequestTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * @tags Export and import
 */
class RequestTransferController extends Controller
{
    /**
     * Export requests.
     *
     * Downloads the requests matching the list's filters (or the given `ids`)
     * as CSV, for Excel (semicolon-separated UTF-8), or as JSON, a full copy
     * that includes the signatures.
     */
    public function export(ExportRequestsRequest $request, RequestTransfer $transfer): Response
    {
        $query = $request->filteredRequests();
        $ids = $request->ids();

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $requests = $query->get();
        $format = $request->validated('format');
        $filename = 'jalaops-pyynnot-'.now()->timezone(config('app.display_timezone'))->format('Y-m-d').".{$format}";

        return response($format === 'csv' ? $transfer->csv($requests) : $transfer->json($requests), 200, [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=utf-8' : 'application/json',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename),
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /**
     * Import requests.
     *
     * Reads a CSV or JSON file (for example an earlier export) and checks each
     * row with the request form's rules. With `dry_run` it only reports what
     * would be imported. Otherwise it imports all rows as new requests, or
     * nothing when any row is invalid. Up to 1000 rows; limited to ten imports
     * per minute per visitor.
     *
     * @response array{dry_run: bool, total: int, valid: int, invalid: int, imported: int, errors: list<array{row: int, title: string|null, messages: list<string>}>, preview: list<array{title: string, priority: string, status: string, due_date: string|null, city: string|null}>}
     */
    public function import(Request $request, RequestTransfer $transfer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt,json'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        try {
            $rows = $transfer->parse($request->file('file'));
        } catch (ImportException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        ['valid' => $valid, 'errors' => $errors] = $transfer->validate($rows);
        $dryRun = $request->boolean('dry_run');

        $result = [
            'dry_run' => $dryRun,
            'total' => count($rows),
            'valid' => count($valid),
            'invalid' => count($errors),
            'imported' => 0,
            'errors' => $errors,
            'preview' => collect($valid)->take(5)->map(fn (array $row) => [
                'title' => $row['title'],
                'priority' => $row['priority'],
                'status' => $row['status'],
                'due_date' => $row['due_date'] ?? null,
                'city' => $row['city'] ?? null,
            ])->values(),
        ];

        if ($dryRun) {
            return response()->json($result);
        }

        if ($errors !== []) {
            return response()->json([...$result, 'message' => 'Tiedostossa on virheellisiä rivejä, joten mitään ei tuotu. Korjaa rivit ja yritä uudelleen.'], 422);
        }

        return response()->json([...$result, 'imported' => $transfer->import($valid)], 201);
    }
}
