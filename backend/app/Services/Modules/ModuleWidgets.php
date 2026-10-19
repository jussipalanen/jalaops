<?php

namespace App\Services\Modules;

use App\Models\ModuleWidgetSetting;
use Illuminate\Support\Facades\Log;

/**
 * Module widgets: what modules show in the app's hooks (config/modules.php),
 * such as a feed on the dashboard. A widget's module.json gives its default
 * hook, modules:place changes it for everyone, and users can still move or
 * hide it in their own browser. A widget is drawn from its module's output
 * for the widget's input, so the module decides what it shows and the app
 * decides how it looks.
 */
class ModuleWidgets
{
    // The placement that hides a widget for everyone.
    public const HIDDEN = 'hidden';

    // The placement that goes back to the widget's own hook.
    public const DEFAULT = 'default';

    // Upper limits for a feed's items.
    private const MAX_ITEMS = 10;

    private const MAX_IMAGE_BYTES = 300_000;

    private const IMAGE_PATTERN = '#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/]+=*$#';

    private const PARAM_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ModuleRunner $runner,
    ) {}

    /**
     * Every widget of every module, with where it's shown.
     *
     * @return list<array{id: string, name: string, title: string, type: string, hook: string, default_hook: string, input: array<string, mixed>, module: PythonModule}>
     */
    public function all(): array
    {
        $settings = ModuleWidgetSetting::query()->pluck('hook', 'widget');
        $widgets = [];

        foreach ($this->modules->all() as $module) {
            foreach ($module->widgets as $widget) {
                $id = "{$module->id}.{$widget['name']}";
                $widgets[] = [
                    ...$widget,
                    'id' => $id,
                    'hook' => $this->placement($settings[$id] ?? null) ?? $widget['hook'],
                    'default_hook' => $widget['hook'],
                    'module' => $module,
                ];
            }
        }

        return $widgets;
    }

    /**
     * Moves a widget to a hook for everyone, hides it ("hidden"), or puts
     * it back in its own hook ("default").
     *
     * @throws ModuleException when there's no such widget or hook
     */
    public function place(string $id, string $hook): array
    {
        $widget = collect($this->all())->firstWhere('id', $id)
            ?? throw new ModuleException("Widget '{$id}' was not found. modules:widgets lists them.");

        if ($hook === self::DEFAULT) {
            ModuleWidgetSetting::query()->whereKey($id)->delete();
        } elseif ($this->placement($hook) !== null) {
            ModuleWidgetSetting::query()->updateOrCreate(['widget' => $id], ['hook' => $hook]);
        } else {
            throw new ModuleException("Unknown place '{$hook}'. Use one of: ".implode(', ', [...array_keys(config('modules.hooks')), self::HIDDEN, self::DEFAULT]).'.');
        }

        return collect($this->all())->firstWhere('id', $widget['id']);
    }

    /**
     * The widgets to show in the hooks whose names start with a prefix
     * (e.g. "dashboard."), each with its items, or an error when its module
     * failed. Widgets of modules that are off, or hidden for everyone, are
     * left out.
     *
     * @return list<array{id: string, module: string, module_name: string, title: string, type: string, hook: string, items: list<array<string, mixed>>, error: string|null}>
     */
    public function render(string $prefix): array
    {
        $shown = array_filter(
            $this->all(),
            fn (array $widget) => $widget['module']->enabled && str_starts_with($widget['hook'], $prefix),
        );

        return array_values(array_map(function (array $widget) {
            try {
                $items = $this->feedItems($this->runner->run($widget['module'], $widget['input']));
                $error = null;
            } catch (ModuleException $exception) {
                Log::warning("Widget '{$widget['id']}': {$exception->getMessage()}");
                $items = [];
                $error = 'Sisällön lataaminen epäonnistui.';
            }

            return [
                'id' => $widget['id'],
                'module' => $widget['module']->id,
                'module_name' => $widget['module']->name,
                'title' => $widget['title'],
                'type' => $widget['type'],
                'hook' => $widget['hook'],
                'items' => $items,
                'error' => $error,
            ];
        }, $shown));
    }

    /**
     * A feed's items from the module's output: {"items": [{"title", "text",
     * "date", "image", "tags", "params"}]}. Only these keys are passed on,
     * and an image only as a small data URL, so the app can draw them safely.
     *
     * @param  array<string, mixed>  $output
     * @return list<array{title: string, text: string|null, date: string|null, image: string|null, tags: list<string>, params: array<string, string>}>
     *
     * @throws ModuleException when the output isn't a feed
     */
    private function feedItems(array $output): array
    {
        $items = $output['items'] ?? null;

        if (! is_array($items) || ! array_is_list($items)) {
            throw new ModuleException('A feed widget must return {"items": [...]}.');
        }

        $feed = [];

        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            if (! is_array($item) || ! is_string($item['title'] ?? null) || trim($item['title']) === '') {
                throw new ModuleException('Each feed item needs a title.');
            }

            $image = $item['image'] ?? null;
            $date = $item['date'] ?? null;
            $params = is_array($item['params'] ?? null) ? $item['params'] : [];

            $feed[] = [
                'title' => mb_substr($item['title'], 0, 200),
                'text' => is_string($item['text'] ?? null) ? mb_substr($item['text'], 0, 300) : null,
                'date' => is_string($date) && strtotime($date) !== false ? $date : null,
                'image' => is_string($image) && strlen($image) <= self::MAX_IMAGE_BYTES * 4 / 3 + 30 && preg_match(self::IMAGE_PATTERN, $image) ? $image : null,
                'tags' => array_values(array_map(
                    fn ($tag) => mb_substr($tag, 0, 30),
                    array_slice(array_filter(is_array($item['tags'] ?? null) ? $item['tags'] : [], 'is_string'), 0, 10),
                )),
                // Parameters for the module's page address (?news=3).
                'params' => array_map(
                    fn ($value) => mb_substr((string) $value, 0, 200),
                    array_filter(
                        $params,
                        fn ($value, $key) => is_string($key) && preg_match(self::PARAM_PATTERN, $key) && (is_string($value) || is_int($value)),
                        ARRAY_FILTER_USE_BOTH,
                    ),
                ),
            ];
        }

        return $feed;
    }

    /**
     * A stored placement: a hook, "hidden", or null for anything else.
     */
    private function placement(?string $hook): ?string
    {
        return $hook === self::HIDDEN || array_key_exists((string) $hook, config('modules.hooks')) ? $hook : null;
    }
}
