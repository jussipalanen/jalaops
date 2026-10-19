<?php

namespace App\Services\Modules;

/**
 * A Python module found by ModuleRegistry: its module.json, where its script
 * is, which package it comes from, and whether it's on. The module's page in the app is either its own
 * `view` (view.html in its folder, shown in a sandboxed frame) or a page the
 * frontend draws from `form` and `results`.
 */
final class PythonModule
{
    /**
     * @param  list<array{name: string, label: string, type: string, help?: string, default?: mixed, options?: list<string>, required?: bool}>  $form
     * @param  list<array{name: string, label: string, decimals?: int}>  $results
     * @param  list<array{path: string, type: 'script'|'style'}>  $assets  CSS and JS files for the view, in order
     * @param  string|null  $package  The Composer package the module comes from, or null for the modules folder
     * @param  bool  $storage  Whether the module gets a data folder of its own (JALAOPS_DATA_DIR)
     * @param  int  $maxInputBytes  The largest input the API sends it, in bytes of JSON
     * @param  list<array{name: string, title: string, type: string, hook: string, input: array<string, mixed>}>  $widgets  What it shows in the app's hooks, e.g. on the dashboard
     * @param  list<array{path: string, params: array<string, string>}>  $routes  The view's pages at addresses of their own, e.g. /news/:news
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $version,
        public readonly string $directory,
        public readonly string $entry,
        public readonly int $timeout,
        public readonly bool $enabled,
        public readonly array $form = [],
        public readonly array $results = [],
        public readonly ?string $view = null,
        public readonly array $assets = [],
        public readonly ?string $package = null,
        public readonly bool $storage = false,
        public readonly int $maxInputBytes = 10_000,
        public readonly array $widgets = [],
        public readonly array $routes = [],
    ) {}

    /**
     * The routes for JSON: params always as an object, also when empty.
     *
     * @return list<array{path: string, params: object}>
     */
    public function routesForJson(): array
    {
        return array_map(fn (array $route) => ['path' => $route['path'], 'params' => (object) $route['params']], $this->routes);
    }

    /**
     * The same module without its routes, when another module has their addresses.
     */
    public function withoutRoutes(): self
    {
        return new self(...[...get_object_vars($this), 'routes' => []]);
    }
}
