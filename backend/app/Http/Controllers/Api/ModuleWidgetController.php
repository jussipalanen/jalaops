<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleWidgets;
use Illuminate\Http\JsonResponse;

/**
 * @tags Modules
 */
class ModuleWidgetController extends Controller
{
    /**
     * List the dashboard's module widgets.
     *
     * The places on the dashboard where widgets can be shown (`hooks`, with
     * their Finnish names), and the widgets of the modules that are on, each
     * with its hook and its items. A widget's module.json gives its hook, and
     * `php artisan modules:place` moves or hides it for everyone. Each item
     * opens the module's page with `params` as the address's parameters
     * (`/tools/modules/news?news=3`). A widget whose module failed has an
     * empty `items` and the reason in `error`.
     *
     * Each widget runs its module, so only the app's own pages may call this,
     * at most 30 times per minute per visitor.
     *
     * @response array{hooks: list<array{id: string, label: string}>, data: list<array{id: string, module: string, module_name: string, title: string, type: string, hook: string, items: list<array{title: string, text: string|null, date: string|null, image: string|null, tags: list<string>, params: array<string, string>}>, error: string|null}>}
     */
    public function dashboard(ModuleWidgets $widgets): JsonResponse
    {
        $hooks = array_filter(config('modules.hooks'), fn (string $hook) => str_starts_with($hook, 'dashboard.'), ARRAY_FILTER_USE_KEY);

        return response()->json([
            'hooks' => array_map(fn (string $id, string $label) => ['id' => $id, 'label' => $label], array_keys($hooks), $hooks),
            'data' => $widgets->render('dashboard.'),
        ]);
    }
}
