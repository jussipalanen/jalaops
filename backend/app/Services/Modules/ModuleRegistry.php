<?php

namespace App\Services\Modules;

use App\Models\ModuleSetting;
use Illuminate\Support\Facades\Log;

/**
 * Finds the Python modules in the modules folder (config/modules.php): each
 * subfolder with a module.json is a module, and the folder name is its id.
 * Whether a module is on comes from modules:enable / modules:disable, or
 * else from the "enabled" in its module.json (off by default).
 */
class ModuleRegistry
{
    // Folder names that can be module ids, so an id can't point outside the folder.
    private const ID_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

    // Upper limit for the timeout a module asks for, in seconds.
    private const MAX_TIMEOUT = 30;

    // Field names in a module's form and results.
    private const FIELD_PATTERN = '/^[a-z][a-z0-9_]*$/';

    // Upper limit for a module's view.html, in bytes.
    private const MAX_VIEW_BYTES = 200_000;

    // Upper limit for a module's CSS and JS files together, in bytes.
    private const MAX_ASSETS_BYTES = 1_000_000;

    // The kinds of files a view can use, by extension.
    private const ASSET_TYPES = ['js' => 'script', 'css' => 'style'];

    // Form field types the app can draw: text, a number, a comma-separated
    // list of numbers, longer text, and a choice from options.
    private const FIELD_TYPES = ['text', 'number', 'numbers', 'textarea', 'select'];

    public function __construct(private readonly string $path) {}

    public static function fromConfig(): self
    {
        return new self(config('modules.path'));
    }

    /**
     * All modules that are set up correctly, by id. Broken ones are logged and skipped.
     *
     * @return array<string, PythonModule>
     */
    public function all(): array
    {
        $settings = ModuleSetting::query()->pluck('enabled', 'module');
        $modules = [];

        foreach (glob(rtrim($this->path, '/').'/*/module.json') ?: [] as $manifest) {
            $id = basename(dirname($manifest));

            try {
                $modules[$id] = $this->load($id, $settings[$id] ?? null);
            } catch (ModuleException $exception) {
                Log::warning($exception->getMessage());
            }
        }

        ksort($modules);

        return $modules;
    }

    /**
     * @throws ModuleException when there's no such module or it's set up wrongly
     */
    public function find(string $id): PythonModule
    {
        return $this->load($id, preg_match(self::ID_PATTERN, $id) ? ModuleSetting::find($id)?->enabled : null);
    }

    /**
     * Turns a module on or off. The setting stays until it's changed again.
     *
     * @throws ModuleException when there's no such module or it's set up wrongly
     */
    public function setEnabled(string $id, bool $enabled): PythonModule
    {
        $this->find($id);

        ModuleSetting::query()->updateOrCreate(['module' => $id], ['enabled' => $enabled]);

        return $this->find($id);
    }

    /**
     * @param  bool|null  $enabled  The stored setting, or null to use module.json's
     */
    private function load(string $id, ?bool $enabled): PythonModule
    {
        $directory = realpath(rtrim($this->path, '/').'/'.$id);

        if (! preg_match(self::ID_PATTERN, $id) || $directory === false || ! is_file("{$directory}/module.json")) {
            throw new ModuleException("Module '{$id}' was not found in {$this->path}.", 'Moduulia ei löytynyt.');
        }

        $manifest = json_decode((string) file_get_contents("{$directory}/module.json"), true);

        if (! is_array($manifest) || ! is_string($manifest['name'] ?? null)) {
            throw new ModuleException("Module '{$id}': module.json must be a JSON object with at least a name.");
        }

        $entry = $this->fileInside($directory, $manifest['entry'] ?? 'main.py');

        if ($entry === null) {
            throw new ModuleException("Module '{$id}': the entry script must be a file inside the module's folder.");
        }

        // The module's own page: "view" in module.json, or else a view.html in its folder.
        $view = isset($manifest['view']) || is_file("{$directory}/view.html")
            ? $this->fileInside($directory, $manifest['view'] ?? 'view.html')
            : null;

        if ((isset($manifest['view']) && $view === null) || ($view !== null && filesize($view) > self::MAX_VIEW_BYTES)) {
            throw new ModuleException("Module '{$id}': the view must be an HTML file inside the module's folder, at most 200 kB.");
        }

        return new PythonModule(
            id: $id,
            name: $manifest['name'],
            description: (string) ($manifest['description'] ?? ''),
            version: (string) ($manifest['version'] ?? ''),
            directory: $directory,
            entry: $entry,
            timeout: max(1, min(self::MAX_TIMEOUT, (int) ($manifest['timeout'] ?? 10))),
            enabled: $enabled ?? ($manifest['enabled'] ?? false) === true,
            form: array_map(fn ($field) => $this->formField($id, $field), $this->listOf($id, $manifest, 'form')),
            results: array_map(fn ($result) => $this->result($id, $result), $this->listOf($id, $manifest, 'results')),
            view: $view,
            assets: $this->assets($id, $directory, $this->listOf($id, $manifest, 'assets')),
        );
    }

    /**
     * The view's CSS and JS files: inside the module's folder, .css or .js,
     * at most 1 MB together.
     *
     * @param  list<mixed>  $files
     * @return list<array{path: string, type: 'script'|'style'}>
     */
    private function assets(string $id, string $directory, array $files): array
    {
        $assets = [];
        $bytes = 0;

        foreach ($files as $file) {
            $path = $this->fileInside($directory, $file);
            $type = self::ASSET_TYPES[strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))] ?? null;

            if ($path === null || $type === null) {
                throw new ModuleException("Module '{$id}': each asset must be a .css or .js file inside the module's folder.");
            }

            $bytes += filesize($path);
            $assets[] = ['path' => $path, 'type' => $type];
        }

        if ($bytes > self::MAX_ASSETS_BYTES) {
            throw new ModuleException("Module '{$id}': the assets can be at most 1 MB together.");
        }

        return $assets;
    }

    /**
     * The real path of a file inside the module's folder, or null when it
     * isn't one (missing, or pointing outside the folder).
     */
    private function fileInside(string $directory, mixed $file): ?string
    {
        $path = is_string($file) ? realpath($directory.'/'.$file) : false;

        return $path !== false && is_file($path) && str_starts_with($path, $directory.DIRECTORY_SEPARATOR) ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<mixed>
     */
    private function listOf(string $id, array $manifest, string $key): array
    {
        $list = $manifest[$key] ?? [];

        if (! is_array($list) || ! array_is_list($list)) {
            throw new ModuleException("Module '{$id}': \"{$key}\" in module.json must be a list.");
        }

        return $list;
    }

    /**
     * A form field, with only the keys the app knows.
     *
     * @return array{name: string, label: string, type: string, help?: string, default?: mixed, options?: list<string>, required?: bool}
     */
    private function formField(string $id, mixed $field): array
    {
        $type = $field['type'] ?? 'text';

        if (! $this->isNamed($field) || ! in_array($type, self::FIELD_TYPES, true)) {
            throw new ModuleException("Module '{$id}': each form field needs a name, a label and a type (".implode(', ', self::FIELD_TYPES).').');
        }

        $options = $field['options'] ?? [];

        if ($type === 'select' && (! is_array($options) || $options === [] || ! array_is_list($options) || array_filter($options, 'is_string') !== $options)) {
            throw new ModuleException("Module '{$id}': the select field '{$field['name']}' needs a list of options.");
        }

        return array_filter([
            'name' => $field['name'],
            'label' => $field['label'],
            'type' => $type,
            'help' => is_string($field['help'] ?? null) ? $field['help'] : null,
            'default' => $field['default'] ?? null,
            'options' => $type === 'select' ? $options : null,
            'required' => ($field['required'] ?? false) === true ? true : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * A result to show, optionally with a fixed number of decimals (e.g. 2 for euros).
     *
     * @return array{name: string, label: string, decimals?: int}
     */
    private function result(string $id, mixed $result): array
    {
        $decimals = $result['decimals'] ?? null;

        if (! $this->isNamed($result) || ($decimals !== null && (! is_int($decimals) || $decimals < 0 || $decimals > 6))) {
            throw new ModuleException("Module '{$id}': each result needs a name and a label, and decimals must be 0–6.");
        }

        return array_filter(['name' => $result['name'], 'label' => $result['label'], 'decimals' => $decimals], fn ($value) => $value !== null);
    }

    private function isNamed(mixed $item): bool
    {
        return is_array($item)
            && is_string($item['name'] ?? null) && preg_match(self::FIELD_PATTERN, $item['name'])
            && is_string($item['label'] ?? null) && trim($item['label']) !== '';
    }
}
