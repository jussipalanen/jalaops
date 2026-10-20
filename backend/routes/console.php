<?php

use App\Services\AiStatusOverview;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleManager;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
use App\Services\Modules\ModuleWidgets;
use App\Services\Modules\PythonModule;
use Composer\InstalledVersions;
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

// Python modules in backend/modules and in module packages (see modules/README.md).
Artisan::command('modules:list', function (ModuleRegistry $modules) {
    $rows = array_map(
        fn (PythonModule $module) => [
            $module->id,
            $module->name,
            $module->version,
            $module->enabled ? 'on' : 'off',
            // A package's installed version comes from Composer, e.g. its git tag.
            match (true) {
                $module->package === null => 'modules',
                InstalledVersions::isInstalled($module->package) => $module->package.' '.InstalledVersions::getPrettyVersion($module->package),
                default => $module->package,
            },
            $module->description,
        ],
        array_values($modules->all()),
    );

    if ($rows === []) {
        $this->info('No modules in '.config('modules.path').' or in packages.');

        return;
    }

    $this->table(['Module', 'Name', 'Version', 'Status', 'Source', 'Description'], $rows);
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

// Module widgets in the app's hooks (config/modules.php), e.g. on the dashboard.
Artisan::command('modules:widgets', function (ModuleWidgets $widgets) {
    $rows = array_map(fn (array $widget) => [
        $widget['id'],
        $widget['title'],
        $widget['hook'].($widget['hook'] === $widget['default_hook'] ? '' : " (default {$widget['default_hook']})"),
        $widget['module']->enabled ? 'on' : 'module off',
    ], $widgets->all());

    if ($rows === []) {
        $this->info('No module has widgets.');

        return;
    }

    $this->table(['Widget', 'Title', 'Hook', 'Status'], $rows);
    $this->line('Hooks: '.implode(', ', array_keys(config('modules.hooks'))));
})->purpose('List the module widgets and where they are shown');

// Where a widget is shown for everyone: a hook, "hidden", or "default" for
// the hook in its module.json. Users can still move it in their own browser.
Artisan::command('modules:place {widget : e.g. news.latest} {hook : A hook, "hidden" or "default"}', function (ModuleWidgets $widgets) {
    try {
        $widget = $widgets->place($this->argument('widget'), $this->argument('hook'));
    } catch (ModuleException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info($widget['hook'] === ModuleWidgets::HIDDEN
        ? "Widget '{$widget['id']}' is now hidden."
        : "Widget '{$widget['id']}' is now shown in {$widget['hook']}.");
})->purpose('Move or hide a module widget for everyone');

// Add a module from a folder, a git URL or an archive (a file or a URL), after
// checking it like an installed one. A module package goes in with Composer;
// anything else is copied into the modules folder. Either way it's a change
// to the code, so production gets it through a pull request and the deploy.
Artisan::command('modules:add
    {source : A folder, an archive (.zip, .tar, .tar.gz, .tgz, .gz; a file or a URL) or a git URL}
    {--ref= : A git tag or branch (a package needs a version tag; by default its newest)}
    {--id= : The module id, when the source doesn\'t tell it}
    {--copy : Copy a module package into the modules folder instead of adding it with Composer}
    {--check : Only check the module, add nothing}
    {--force : Replace a module in the modules folder that has the same id}
    {--skip-tests : Don\'t run the module\'s own tests}', function (ModuleManager $manager) {
    if (app()->isProduction() && ! $this->option('check') && ! $this->confirm(
        'This is a production server. A module added here is lost when the server is redeployed or restarted (on Cloud Run, every instance has its own temporary files), and it skips code review. Add it through a pull request instead. Add it here anyway?',
    )) {
        return 1;
    }

    try {
        $this->line("Fetching {$this->argument('source')}…");
        $source = $manager->fetch($this->argument('source'), $this->option('ref'), $this->option('copy'));

        try {
            $this->line('Checking the module'.($this->option('skip-tests') ? '' : ' and running its tests').'…');
            ['modules' => $modules, 'warnings' => $warnings] = $manager->check($source, $this->option('id'), $this->option('force'), ! $this->option('skip-tests'));

            foreach ($modules as $module) {
                $this->info("✓ {$module->id}: {$module->name}".($module->version !== '' ? " {$module->version}" : '').' is valid.');
            }

            foreach ($warnings as $warning) {
                $this->warn($warning);
            }

            if ($this->option('check')) {
                return 0;
            }

            foreach ($manager->install($source, $modules, $this->option('force')) as $change) {
                $this->info($change);
            }
        } finally {
            $manager->cleanup($source);
        }
    } catch (ModuleException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    foreach ($modules as $module) {
        $this->line($module->enabled
            ? "Module '{$module->id}' is on (its module.json turns it on)."
            : "Module '{$module->id}' is off until it's turned on in Työkalut → Moduulit or with modules:enable {$module->id}.");
    }

    $this->line('Commit the changes and open a pull request.');
})->purpose('Add a Python module from a folder, an archive or a git URL, after checking it');

Artisan::command('modules:create
    {module : The new module\'s id (a-z, 0-9 and -), also its folder name}
    {--name= : The name users see, in Finnish (default: from the id)}', function (ModuleManager $manager) {
    try {
        $files = $manager->create($this->argument('module'), $this->option('name'));
    } catch (ModuleException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $id = $this->argument('module');
    $this->info("Created module '{$id}':");
    foreach ($files as $file) {
        $this->line("  {$file}");
    }
    $this->line("Try it: ./dev artisan modules:run {$id} '{\"name\": \"Maailma\"}'. Its tests run with python3 -m unittest in its folder.");
})->purpose('Create a new Python module that uses the module SDK');

Artisan::command('modules:remove
    {module}
    {--purge : Also delete its data folder and its settings}
    {--force : Don\'t ask for confirmation}', function (ModuleManager $manager, ModuleRegistry $modules) {
    $id = $this->argument('module');
    $location = $modules->locate($id);

    if ($location === null) {
        $this->error("Module '{$id}' was not found. modules:list lists them.");

        return 1;
    }

    $what = $location['package'] !== null ? "the package {$location['package']}" : 'its folder';
    $question = "Remove module '{$id}' ({$what})".($this->option('purge') ? ' and delete its data and settings' : '').'?'
        .(app()->isProduction() ? ' This is a production server: the next deploy brings it back unless it\'s removed from the code too.' : '');

    if (! $this->option('force') && ! $this->confirm($question)) {
        $this->line('Nothing was removed.');

        return 1;
    }

    try {
        foreach ($manager->remove($id, $this->option('purge')) as $change) {
            $this->info($change);
        }
    } catch (ModuleException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->line('Commit the changes and open a pull request.');
})->purpose('Remove a Python module');
