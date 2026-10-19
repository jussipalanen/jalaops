<?php

namespace App\Services\Modules;

use App\Models\ModuleSetting;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Finds the Python modules in the modules folder (config/modules.php) and in
 * the installed Composer packages of type "jalaops-module". In a folder, each
 * subfolder with a module.json is a module, and the folder name is its id. A
 * package with a module.json at its root is one module, named after the
 * package (jalaops/weather is "weather"); otherwise it's a folder of modules.
 * Whether a module is on comes from modules:enable / modules:disable, or
 * else from the "enabled" in its module.json (off by default). A module's
 * routes give its view's pages addresses of their own (/news/:news); each
 * first part of an address belongs to one module.
 */
class ModuleRegistry
{
    // The Composer package type of module packages.
    public const PACKAGE_TYPE = 'jalaops-module';

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

    // The input the API sends a module by default, and the most a module can
    // ask for with "max_input_kb" (images as data URLs need more). Vercel
    // forwards at most 4.5 MB to the backend.
    private const DEFAULT_INPUT_KB = 10;

    private const MAX_INPUT_KB = 4096;

    // The kinds of widgets the app can draw: "feed" is a list of items with
    // a title, text, date and image, each opening the module's page.
    private const WIDGET_TYPES = ['feed'];

    // A route's path: parts of lowercase letters, numbers and hyphens, or
    // :name for a parameter, starting with a fixed part (/news/:news/edit).
    private const ROUTE_PATTERN = '#^/[a-z0-9][a-z0-9-]*(/([a-z0-9][a-z0-9-]*|:[a-z][a-z0-9_]*))*$#';

    // Upper limits for a module's routes, and for the parts of one path.
    private const MAX_ROUTES = 10;

    private const MAX_ROUTE_PARTS = 6;

    /**
     * @param  string  $path  The app's own modules folder
     * @param  array<string, string>  $packages  Module packages: name => install path
     */
    public function __construct(
        private readonly string $path,
        private readonly array $packages = [],
    ) {}

    public static function fromConfig(): self
    {
        $packages = config('modules.packages') ? self::installedPackages(InstalledVersions::getAllRawData()) : [];

        return new self(config('modules.path'), $packages);
    }

    /**
     * The module packages in Composer's installed data (InstalledVersions::getAllRawData()).
     *
     * @param  list<array{versions?: array<string, array{type?: string, install_path?: string}>}>  $installed
     * @return array<string, string> name => install path
     */
    public static function installedPackages(array $installed): array
    {
        $packages = [];

        foreach ($installed as $dataset) {
            foreach ($dataset['versions'] ?? [] as $name => $package) {
                // Packages that are only provided or replaced have no install path.
                if (($package['type'] ?? null) === self::PACKAGE_TYPE && isset($package['install_path'])) {
                    $packages[$name] ??= $package['install_path'];
                }
            }
        }

        ksort($packages);

        return $packages;
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

        foreach ($this->locations() as $id => $location) {
            try {
                $modules[$id] = $this->load($id, $location, $settings[$id] ?? null);
            } catch (ModuleException $exception) {
                Log::warning($exception->getMessage());
            }
        }

        ksort($modules);

        return $this->withoutClashingRoutes($modules);
    }

    /**
     * @throws ModuleException when there's no such module or it's set up wrongly
     */
    public function find(string $id): PythonModule
    {
        if (! preg_match(self::ID_PATTERN, $id)) {
            return $this->load($id, null, null);
        }

        $module = $this->load($id, $this->locations()[$id] ?? null, ModuleSetting::find($id)?->enabled);

        // Whether another module has its addresses is known only among all of them.
        return $module->routes === [] ? $module : ($this->all()[$id] ?? $module);
    }

    /**
     * Loads a module from any folder with the same checks as an installed
     * one, e.g. to check a module before it's added (modules:add). Whether
     * it's on is what it would be once added.
     *
     * @throws ModuleException when it's set up wrongly
     */
    public function inspect(string $directory, string $id): PythonModule
    {
        return $this->load($id, ['directory' => $directory, 'package' => null], ModuleSetting::find($id)?->enabled);
    }

    /**
     * Where an installed module is, also when it's set up wrongly, or null.
     *
     * @return array{directory: string, package: string|null}|null
     */
    public function locate(string $id): ?array
    {
        return preg_match(self::ID_PATTERN, $id) ? ($this->locations()[$id] ?? null) : null;
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
     * Each first part of an address (/news) belongs to the first module by
     * id that uses it. A module that uses another's keeps working at
     * /tools/modules/<id>, but without its routes.
     *
     * @param  array<string, PythonModule>  $modules
     * @return array<string, PythonModule>
     */
    private function withoutClashingRoutes(array $modules): array
    {
        $owners = [];

        foreach ($modules as $id => $module) {
            $firsts = array_unique(array_map(fn (array $route) => explode('/', $route['path'])[1], $module->routes));
            $taken = array_filter($firsts, fn (string $first) => isset($owners[$first]));

            if ($taken !== []) {
                $first = reset($taken);
                Log::warning("Module '{$id}': its routes were skipped, module '{$owners[$first]}' already has /{$first}.");
                $modules[$id] = $module->withoutRoutes();

                continue;
            }

            foreach ($firsts as $first) {
                $owners[$first] = $id;
            }
        }

        return $modules;
    }

    /**
     * Where each module is, by id: the modules folder first, then the
     * packages. A module whose id is already taken is logged and skipped.
     *
     * @return array<string, array{directory: string, package: string|null}>
     */
    private function locations(): array
    {
        $found = [];

        foreach ($this->folderOfModules($this->path) as $id => $directory) {
            $found[] = [$id, $directory, null];
        }

        foreach ($this->packages as $package => $installPath) {
            $modules = is_file("{$installPath}/module.json")
                ? [Str::afterLast($package, '/') => $installPath]
                : $this->folderOfModules($installPath);

            foreach ($modules as $id => $directory) {
                $found[] = [$id, $directory, $package];
            }
        }

        $locations = [];

        foreach ($found as [$id, $directory, $package]) {
            if (isset($locations[$id])) {
                Log::warning("Module '{$id}' in {$directory} was skipped: {$locations[$id]['directory']} already has that id.");

                continue;
            }

            $locations[$id] = ['directory' => $directory, 'package' => $package];
        }

        return $locations;
    }

    /**
     * The subfolders of a folder that have a module.json, by folder name.
     *
     * @return array<string, string>
     */
    private function folderOfModules(string $path): array
    {
        $modules = [];

        foreach (glob(rtrim($path, '/').'/*/module.json') ?: [] as $manifest) {
            $modules[basename(dirname($manifest))] = dirname($manifest);
        }

        return $modules;
    }

    /**
     * @param  array{directory: string, package: string|null}|null  $location  Where the module is, or null when nowhere
     * @param  bool|null  $enabled  The stored setting, or null to use module.json's
     */
    private function load(string $id, ?array $location, ?bool $enabled): PythonModule
    {
        $directory = $location === null ? false : realpath($location['directory']);

        if (! preg_match(self::ID_PATTERN, $id) || $directory === false || ! is_file("{$directory}/module.json")) {
            throw new ModuleException("Module '{$id}' was not found.", 'Moduulia ei löytynyt.');
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
            package: $location['package'],
            storage: ($manifest['storage'] ?? false) === true,
            maxInputBytes: $this->maxInputKb($id, $manifest) * 1000,
            widgets: array_map(fn ($widget) => $this->widget($id, $widget), $this->listOf($id, $manifest, 'widgets')),
            routes: $this->routes($id, $this->listOf($id, $manifest, 'routes')),
        );
    }

    /**
     * The view's pages at addresses of their own: a path, and fixed
     * parameters the page gets besides the path's (/news/new gives view=new).
     *
     * @param  list<mixed>  $routes
     * @return list<array{path: string, params: array<string, string>}>
     */
    private function routes(string $id, array $routes): array
    {
        if (count($routes) > self::MAX_ROUTES) {
            throw new ModuleException("Module '{$id}': a module can have at most ".self::MAX_ROUTES.' routes.');
        }

        $reserved = config('modules.reserved_paths');
        $patterns = [];

        return array_map(function ($route) use ($id, $reserved, &$patterns) {
            $path = is_array($route) ? ($route['path'] ?? null) : null;
            $params = is_array($route) ? ($route['params'] ?? []) : null;

            if (! is_string($path) || ! preg_match(self::ROUTE_PATTERN, $path) || substr_count($path, '/') > self::MAX_ROUTE_PARTS) {
                throw new ModuleException("Module '{$id}': each route needs a path like /news or /news/:news (a-z, 0-9, -, at most ".self::MAX_ROUTE_PARTS.' parts, the first one fixed).');
            }

            $parts = explode('/', substr($path, 1));
            $names = array_map(fn (string $part) => substr($part, 1), array_filter($parts, fn (string $part) => $part[0] === ':'));

            if (in_array($parts[0], $reserved, true)) {
                throw new ModuleException("Module '{$id}': the route {$path} can't start with /{$parts[0]}, the app uses it.");
            }

            if (
                ! is_array($params) || ($params !== [] && array_is_list($params))
                || array_filter(array_keys($params), fn ($name) => ! is_string($name) || ! preg_match(self::FIELD_PATTERN, $name)) !== []
                || array_filter($params, fn ($value) => ! is_string($value) || $value === '' || mb_strlen($value) > 200) !== []
                || count(array_unique($names)) !== count($names)
                || array_intersect($names, array_keys($params)) !== []
            ) {
                throw new ModuleException("Module '{$id}': the route {$path} needs different names for its parameters, and its params must be an object of short texts.");
            }

            // /news/:news and /news/:id are the same address.
            $pattern = preg_replace('#/:[a-z0-9_]+#', '/:', $path);

            if (isset($patterns[$pattern])) {
                throw new ModuleException("Module '{$id}': two routes have the address {$path}.");
            }

            $patterns[$pattern] = true;

            return ['path' => $path, 'params' => $params];
        }, $routes);
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

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function maxInputKb(string $id, array $manifest): int
    {
        $kb = $manifest['max_input_kb'] ?? self::DEFAULT_INPUT_KB;

        if (! is_int($kb) || $kb < 1 || $kb > self::MAX_INPUT_KB) {
            throw new ModuleException("Module '{$id}': max_input_kb must be a whole number from 1 to ".self::MAX_INPUT_KB.'.');
        }

        return $kb;
    }

    /**
     * A widget the module shows in one of the app's hooks (config/modules.php).
     *
     * @return array{name: string, title: string, type: string, hook: string, input: array<string, mixed>}
     */
    private function widget(string $id, mixed $widget): array
    {
        $hooks = array_keys(config('modules.hooks'));
        $input = $widget['input'] ?? [];

        if (
            ! is_array($widget)
            || ! is_string($widget['name'] ?? null) || ! preg_match(self::ID_PATTERN, $widget['name'])
            || ! is_string($widget['title'] ?? null) || trim($widget['title']) === ''
            || ! in_array($widget['type'] ?? 'feed', self::WIDGET_TYPES, true)
            || ! in_array($widget['hook'] ?? null, $hooks, true)
            || ! is_array($input) || ($input !== [] && array_is_list($input))
        ) {
            throw new ModuleException("Module '{$id}': each widget needs a name (a-z, 0-9, -), a title, a type (".implode(', ', self::WIDGET_TYPES).'), a hook ('.implode(', ', $hooks).') and an input object.');
        }

        return [
            'name' => $widget['name'],
            'title' => $widget['title'],
            'type' => $widget['type'] ?? 'feed',
            'hook' => $widget['hook'],
            'input' => $input,
        ];
    }

    private function isNamed(mixed $item): bool
    {
        return is_array($item)
            && is_string($item['name'] ?? null) && preg_match(self::FIELD_PATTERN, $item['name'])
            && is_string($item['label'] ?? null) && trim($item['label']) !== '';
    }
}
