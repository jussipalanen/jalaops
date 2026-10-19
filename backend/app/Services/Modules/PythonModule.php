<?php

namespace App\Services\Modules;

/**
 * A Python module found by ModuleRegistry: its module.json, where its script
 * is, and whether it's on. The module's page in the app is either its own
 * `view` (view.html in its folder, shown in a sandboxed frame) or a page the
 * frontend draws from `form` and `results`.
 */
final class PythonModule
{
    /**
     * @param  list<array{name: string, label: string, type: string, help?: string, default?: mixed, options?: list<string>, required?: bool}>  $form
     * @param  list<array{name: string, label: string, decimals?: int}>  $results
     * @param  list<array{path: string, type: 'script'|'style'}>  $assets  CSS and JS files for the view, in order
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
    ) {}
}
