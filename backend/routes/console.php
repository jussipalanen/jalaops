<?php

use App\Services\AiStatusOverview;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
use App\Services\Modules\PythonModule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generates the AI status overview ahead of time, so the first visitor after a
// deploy doesn't wait for the AI. backend/docker/start.sh runs it at startup.
Artisan::command('ai-overview:warm', function (AiStatusOverview $overview) {
    if (! $overview->isEnabled()) {
        $this->info('AI status overview is off; nothing to do.');

        return;
    }

    try {
        $result = $overview->get();
        $result = $result['outdated'] ? $overview->refresh() : $result;
        $this->info("AI status overview ready (generated {$result['generated_at']}).");
    } catch (Throwable $exception) {
        $this->warn('AI status overview could not be generated: '.$exception->getMessage());
    }
})->purpose('Generate the AI status overview ahead of time');

// Python modules in backend/modules (see modules/README.md).
Artisan::command('modules:list', function (ModuleRegistry $modules) {
    $rows = array_map(
        fn (PythonModule $module) => [$module->id, $module->name, $module->version, $module->enabled ? 'on' : 'off', $module->description],
        array_values($modules->all()),
    );

    if ($rows === []) {
        $this->info('No modules in '.config('modules.path').'.');

        return;
    }

    $this->table(['Module', 'Name', 'Version', 'Status', 'Description'], $rows);
})->purpose('List the Python modules');

Artisan::command('modules:run {module} {input? : A JSON object for the module}', function (ModuleRegistry $modules, ModuleRunner $runner) {
    $input = json_decode($this->argument('input') ?? '{}', true);

    if (! is_array($input) || ($input !== [] && array_is_list($input))) {
        $this->error('The input must be a JSON object, e.g. \'{"name": "Maailma"}\'.');

        return 1;
    }

    try {
        $output = $runner->run($modules->find($this->argument('module')), $input);
    } catch (ModuleException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
})->purpose('Run a Python module with a JSON input and print its JSON output');

// Turn a module on or off; the setting is stored in the database and
// overrides the "enabled" in the module's module.json.
foreach (['enable' => true, 'disable' => false] as $action => $enabled) {
    Artisan::command("modules:{$action} {module}", function (ModuleRegistry $modules) use ($enabled) {
        try {
            $module = $modules->setEnabled($this->argument('module'), $enabled);
        } catch (ModuleException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->info("Module '{$module->id}' is now ".($module->enabled ? 'on' : 'off').'.');
    })->purpose(($enabled ? 'Turn on' : 'Turn off').' a Python module');
}
