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
    /**
     * List the Python modules.
     *
     * Every module and whether it's on. Modules are optional: users turn them
     * on and off in the app (`PATCH /api/modules/{id}`), and the server with
     * `php artisan modules:enable` and `modules:disable`. `routes` are the
     * addresses of the module's pages in the app (`/news/:news`), with the
     * fixed parameters each page gets; the app uses the routes of modules
     * that are on.
     *
     * @response array{data: list<array{id: string, name: string, description: string, version: string, enabled: bool, routes: list<array{path: string, params: array<string, string>}>}>}
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
     * @response array{data: array{id: string, name: string, description: string, version: string, enabled: bool, routes: list<array{path: string, params: array<string, string>}>, form: list<array{name: string, label: string, type: string, help?: string, default?: mixed, options?: list<string>, required?: bool}>, results: list<array{name: string, label: string, decimals?: int}>, view: string|null, assets: list<array{type: 'script'|'style', content: string}>}}
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
     * Turn a Python module on or off.
     *
     * The setting is stored on the server, so it's the same for everyone, and
     * it overrides the `enabled` in the module's module.json. Only the app's
     * own pages may call this.
     *
     * @response array{data: array{id: string, name: string, description: string, version: string, enabled: bool, routes: list<array{path: string, params: array<string, string>}>}}
     */
    public function update(Request $request, ModuleRegistry $modules, string $module): JsonResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $this->find($modules, $module);

        return response()->json(['data' => $this->summary($modules->setEnabled($module, (bool) $validated['enabled']))]);
    }

    /**
     * Run a Python module.
     *
     * The request body, a JSON object, is the module's input, and `output` is
     * what the module returned. The input can be at most 10 kB, or what the
     * module's `max_input_kb` allows (at most 4 MB, e.g. for images). Only the
     * app's own pages may call this (see `ALLOWED_ORIGINS`), at most 30 times
     * per minute per visitor. A module that is off answers 403; a module that
     * fails answers 422 with the reason in `reason`.
     *
     * @response array{output: array<string, mixed>}
     */
    public function run(Request $request, ModuleRegistry $modules, ModuleRunner $runner, string $module): JsonResponse
    {
        $found = $this->find($modules, $module);

        if (! $found->enabled) {
            return response()->json(['message' => 'Moduuli ei ole käytössä.'], 403);
        }

        // The body as it was sent: Laravel's middleware would trim strings and
        // turn empty ones into null in $request->json().
        $content = $request->getContent();
        $input = $content === '' ? [] : json_decode($content, true);

        if (strlen($content) > $found->maxInputBytes || ! is_array($input) || ($input !== [] && array_is_list($input))) {
            $limit = $found->maxInputBytes >= 1_000_000
                ? str_replace('.', ',', (string) round($found->maxInputBytes / 1_000_000, 1)).' Mt'
                : intdiv($found->maxInputBytes, 1000).' kt';

            return response()->json(['message' => "Syötteen pitää olla JSON-objekti, enintään {$limit}."], 422);
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
     * @return array{id: string, name: string, description: string, version: string, enabled: bool, routes: list<array{path: string, params: array<string, string>}>}
     */
    private function summary(PythonModule $module): array
    {
        return [
            'id' => $module->id,
            'name' => $module->name,
            'description' => $module->description,
            'version' => $module->version,
            'enabled' => $module->enabled,
            'routes' => $module->routesForJson(),
        ];
    }
}
