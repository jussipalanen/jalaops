<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
use App\Services\Modules\PythonModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags Modules
 */
class ModuleController extends Controller
{
    // Upper limit for a module's input, in bytes of JSON.
    private const MAX_INPUT_BYTES = 10_000;

    /**
     * List the Python modules.
     *
     * Every module in the backend's modules folder and whether it's on. Modules
     * are turned on and off on the server with `php artisan modules:enable` and
     * `modules:disable`, not through the API.
     *
     * @response array{data: list<array{id: string, name: string, description: string, version: string, enabled: bool}>}
     */
    public function index(ModuleRegistry $modules): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_map(fn (PythonModule $module) => $this->summary($module), $modules->all())),
        ]);
    }

    /**
     * Get a Python module.
     *
     * The module with what the app needs for its page: `view`, the module's
     * own HTML (shown in a sandboxed frame) with the CSS and JS files it uses
     * in `assets`, or else the form fields and results the app draws a page
     * from. `view` is null and `assets` empty when the module has no view or
     * is off. The files are sent inside JSON, never as pages of their own, so
     * they can't run on the app's address.
     *
     * @response array{data: array{id: string, name: string, description: string, version: string, enabled: bool, form: list<array{name: string, label: string, type: string, help?: string, default?: mixed, options?: list<string>, required?: bool}>, results: list<array{name: string, label: string, decimals?: int}>, view: string|null, assets: list<array{type: 'script'|'style', content: string}>}}
     */
    public function show(ModuleRegistry $modules, string $module): JsonResponse
    {
        $found = $this->find($modules, $module);
        $hasView = $found->enabled && $found->view !== null;

        return response()->json([
            'data' => [
                ...$this->summary($found),
                'form' => $found->form,
                'results' => $found->results,
                'view' => $hasView ? file_get_contents($found->view) : null,
                'assets' => $hasView ? array_map(fn (array $asset) => [
                    'type' => $asset['type'],
                    'content' => file_get_contents($asset['path']),
                ], $found->assets) : [],
            ],
        ]);
    }

    /**
     * Run a Python module.
     *
     * The request body, a JSON object, is the module's input, and `output` is
     * what the module returned. Only the app's own pages may call this (see
     * `ALLOWED_ORIGINS`), at most ten times per minute per visitor. A module
     * that is off answers 403; a module that fails answers 422 with the reason
     * in `reason`.
     *
     * @response array{output: array<string, mixed>}
     */
    public function run(Request $request, ModuleRegistry $modules, ModuleRunner $runner, string $module): JsonResponse
    {
        $found = $this->find($modules, $module);

        if (! $found->enabled) {
            return response()->json(['message' => 'Moduuli ei ole käytössä.'], 403);
        }

        $input = $request->json()->all();

        if (strlen($request->getContent()) > self::MAX_INPUT_BYTES || ($input !== [] && array_is_list($input))) {
            return response()->json(['message' => 'Syötteen pitää olla JSON-objekti, enintään 10 kt.'], 422);
        }

        try {
            return response()->json(['output' => $runner->run($found, $input)]);
        } catch (ModuleException $exception) {
            Log::warning($exception->getMessage());

            return response()->json([
                'message' => 'Moduulin suoritus epäonnistui.',
                'reason' => $exception->reason,
            ], 422);
        }
    }

    private function find(ModuleRegistry $modules, string $id): PythonModule
    {
        try {
            return $modules->find($id);
        } catch (ModuleException $exception) {
            Log::warning($exception->getMessage());

            abort(response()->json(['message' => 'Moduulia ei löytynyt.'], 404));
        }
    }

    /**
     * @return array{id: string, name: string, description: string, version: string, enabled: bool}
     */
    private function summary(PythonModule $module): array
    {
        return [
            'id' => $module->id,
            'name' => $module->name,
            'description' => $module->description,
            'version' => $module->version,
            'enabled' => $module->enabled,
        ];
    }
}
