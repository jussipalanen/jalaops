<?php

namespace Tests\Feature;

use App\Models\ModuleSetting;
use App\Models\ModuleWidgetSetting;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PharData;
use Tests\TestCase;
use ZipArchive;

/**
 * modules:add and modules:remove. The modules folder is a temporary one with
 * the "routes" fixture module (which has /notes), and the sources are made
 * from tests/Fixtures/module-sources/weather.
 */
class ModuleCommandsTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    private string $modules;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporary = sys_get_temp_dir().'/jalaops-module-commands-'.uniqid();
        $this->modules = "{$this->temporary}/modules";
        $this->storage = "{$this->temporary}/storage";
        File::copyDirectory(base_path('tests/Fixtures/modules/routes'), "{$this->modules}/routes");

        config(['modules.path' => $this->modules, 'modules.packages' => false, 'modules.storage' => $this->storage]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporary);

        parent::tearDown();
    }

    /**
     * A copy of the weather module in a folder of this name, with files
     * changed (null deletes one).
     *
     * @param  array<string, string|null>  $files
     */
    private function source(string $name = 'weather', array $files = []): string
    {
        $directory = "{$this->temporary}/sources/{$name}";
        File::copyDirectory(base_path('tests/Fixtures/module-sources/weather'), $directory);

        foreach ($files as $file => $content) {
            if ($content === null) {
                File::delete("{$directory}/{$file}");
            } else {
                File::ensureDirectoryExists(dirname("{$directory}/{$file}"));
                File::put("{$directory}/{$file}", $content);
            }
        }

        return $directory;
    }

    /**
     * A git repository of a source, with these tags on its one commit.
     */
    private function repository(string $directory, array $tags = []): string
    {
        $git = fn (array $arguments) => Process::path($directory)->run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', ...$arguments])->throw();

        $git(['init', '--quiet']);
        $git(['add', '.']);
        $git(['commit', '--quiet', '-m', 'Module']);
        foreach ($tags as $tag) {
            $git(['tag', $tag]);
        }

        return "file://{$directory}";
    }

    private function zip(string $directory, string $root, array $extra = []): string
    {
        $file = "{$this->temporary}/{$root}.zip";
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE);

        foreach (File::allFiles($directory) as $item) {
            $zip->addFile($item->getPathname(), "{$root}/{$item->getRelativePathname()}");
        }

        foreach ($extra as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $file;
    }

    public function test_it_adds_a_module_from_a_folder(): void
    {
        $source = $this->source('jalaops-module-weather', ['.git/config' => 'x', '__pycache__/main.cpython-313.pyc' => 'x']);

        $this->artisan('modules:add', ['source' => $source])
            ->expectsOutputToContain('✓ weather: Sää 1.0.0 is valid.')
            ->expectsOutputToContain("Added module 'weather' (Sää) to")
            ->expectsOutputToContain("Module 'weather' is off until it's turned on")
            ->assertExitCode(0);

        $this->assertFileExists("{$this->modules}/weather/main.py");
        $this->assertFileExists("{$this->modules}/weather/tests/test_main.py");
        // Git and Python's caches stay out.
        $this->assertDirectoryDoesNotExist("{$this->modules}/weather/.git");
        $this->assertDirectoryDoesNotExist("{$this->modules}/weather/__pycache__");
        $this->assertSame('Sää', app(ModuleRegistry::class)->find('weather')->name);
    }

    public function test_check_adds_nothing(): void
    {
        $this->artisan('modules:add', ['source' => $this->source(), '--check' => true])
            ->expectsOutputToContain('✓ weather: Sää 1.0.0 is valid.')
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist("{$this->modules}/weather");
    }

    public function test_a_module_must_be_valid(): void
    {
        foreach ([
            'module.json' => [['module.json' => '{"description": "no name"}'], "The module isn't valid: Module 'weather': module.json must be a JSON object"],
            'escape' => [['module.json' => '{"name": "Sää", "entry": "../main.py"}'], 'the entry script must be a file inside'],
            'reserved route' => [['module.json' => '{"name": "Sää", "routes": [{"path": "/tools/weather"}]}'], "can't start with /tools"],
            'taken route' => [['module.json' => '{"name": "Sää", "routes": [{"path": "/notes/:id"}]}'], "The address /notes is already module 'routes''s"],
            'syntax' => [['helpers.py' => "def broken(:\n"], "The module's Python doesn't compile: helpers.py:1:"],
            'tests' => [['tests/test_fail.py' => "import unittest\n\nclass T(unittest.TestCase):\n    def test_fail(self):\n        self.fail('rikki')\n"], "The module's own tests failed"],
            'no module.json' => [['module.json' => null], 'There is no module.json in the source.'],
        ] as $case => [$files, $message]) {
            $this->artisan('modules:add', ['source' => $this->source("case-{$case}", $files), '--id' => 'weather'])
                ->expectsOutputToContain($message)
                ->assertExitCode(1);
        }

        $this->assertDirectoryDoesNotExist("{$this->modules}/weather");
    }

    public function test_its_tests_can_be_skipped(): void
    {
        $source = $this->source('weather', ['tests/test_fail.py' => "import unittest\n\nclass T(unittest.TestCase):\n    def test_fail(self):\n        self.fail('rikki')\n"]);

        $this->artisan('modules:add', ['source' => $source, '--skip-tests' => true])->assertExitCode(0);
        $this->assertFileExists("{$this->modules}/weather/main.py");
    }

    public function test_a_module_cant_have_links(): void
    {
        $source = $this->source();
        symlink('/etc/passwd', "{$source}/passwd");

        $this->artisan('modules:add', ['source' => $source])
            ->expectsOutputToContain('The module has a symbolic link (passwd)')
            ->assertExitCode(1);
    }

    public function test_an_id_is_needed_and_must_be_free(): void
    {
        $this->artisan('modules:add', ['source' => $this->source('Weather_Module')])
            ->expectsOutputToContain("'weather_module' can't be a module id (a-z, 0-9 and -): give one with --id.")
            ->assertExitCode(1);

        $this->artisan('modules:add', ['source' => $this->source()])->assertExitCode(0);
        File::put("{$this->modules}/weather/old.txt", 'old');

        $this->artisan('modules:add', ['source' => $this->source()])
            ->expectsOutputToContain("A module with the id 'weather' is already installed (")
            ->assertExitCode(1);

        // --force replaces the folder.
        $this->artisan('modules:add', ['source' => $this->source(), '--force' => true])->assertExitCode(0);
        $this->assertFileDoesNotExist("{$this->modules}/weather/old.txt");
    }

    public function test_it_adds_a_module_from_archives(): void
    {
        $zip = $this->zip($this->source(), 'jalaops-module-weather');
        $this->artisan('modules:add', ['source' => $zip])->expectsOutputToContain("Added module 'weather'")->assertExitCode(0);

        // A .tar.gz, and a .gz with a tar inside, from files.
        foreach (['tar.gz' => 'sunny', 'gz' => 'cloudy'] as $ending => $id) {
            $tar = new PharData("{$this->temporary}/{$id}.tar");
            // Its own route: /weather is the zip's.
            $tar->buildFromDirectory($this->source($id, ['module.json' => "{\"name\": \"Sää\", \"routes\": [{\"path\": \"/{$id}\"}]}"]));
            $tar->compress(\Phar::GZ);
            $file = "{$this->temporary}/{$id}.{$ending}";
            if ($ending === 'gz') {
                rename("{$this->temporary}/{$id}.tar.gz", $file);
            }

            $this->artisan('modules:add', ['source' => $file, '--id' => $id])->expectsOutputToContain("Added module '{$id}'")->assertExitCode(0);
            $this->assertFileExists("{$this->modules}/{$id}/main.py");
        }

        // A .zip from a URL.
        Http::fake(['example.test/*' => Http::response(file_get_contents($this->zip($this->source('rainy', ['module.json' => '{"name": "Sää"}']), 'rainy')))]);
        $this->artisan('modules:add', ['source' => 'https://example.test/releases/weather.zip?v=1', '--id' => 'rainy'])
            ->expectsOutputToContain("Added module 'rainy'")
            ->assertExitCode(0);
    }

    public function test_an_archive_cant_write_outside_its_folder(): void
    {
        $zip = $this->zip($this->source(), 'weather', ['../evil.py' => 'print(1)']);
        $this->artisan('modules:add', ['source' => $zip])->expectsOutputToContain('The archive has an unsafe path: ../evil.py')->assertExitCode(1);

        // A link in a tar archive.
        $source = $this->source('linked');
        symlink('/etc', "{$source}/etc");
        Process::path(dirname($source))->run(['tar', '-czf', "{$this->temporary}/linked.tar.gz", 'linked'])->throw();
        $this->artisan('modules:add', ['source' => "{$this->temporary}/linked.tar.gz"])->expectsOutputToContain('The archive has a link or a special file')->assertExitCode(1);

        $this->assertFileDoesNotExist("{$this->modules}/../evil.py");
    }

    public function test_it_adds_a_module_from_a_git_repository(): void
    {
        $url = $this->repository($this->source('jalaops-module-weather'), ['v1.0.0']);

        $this->artisan('modules:add', ['source' => $url, '--ref' => 'v1.0.0'])
            ->expectsOutputToContain("Added module 'weather'")
            ->assertExitCode(0);

        $this->assertFileExists("{$this->modules}/weather/main.py");
        $this->assertDirectoryDoesNotExist("{$this->modules}/weather/.git");
    }

    public function test_a_package_is_added_with_composer_at_its_newest_version(): void
    {
        Process::fake(["'composer' *" => Process::result()]);
        $url = $this->repository($this->source('jalaops-module-weather', [
            'composer.json' => '{"name": "jalaops/weather", "type": "jalaops-module"}',
        ]), ['v1.2.0', 'v1.10.0', 'not-a-version']);

        $this->artisan('modules:add', ['source' => $url])
            ->expectsOutputToContain('✓ weather: Sää 1.0.0 is valid.')
            ->expectsOutputToContain('Added the repository jalaops-module-weather to composer.json.')
            ->expectsOutputToContain('Added the package jalaops/weather:^1.10 at v1.10.0')
            ->assertExitCode(0);

        Process::assertRan(fn (PendingProcess $process) => $process->command === ['composer', 'config', 'repositories.jalaops-module-weather', 'vcs', $url, '--no-interaction']);
        Process::assertRan(fn (PendingProcess $process) => $process->command === ['composer', 'require', 'jalaops/weather:^1.10', '--no-interaction']);
        // Composer installs it into modules/packages, not as a copy.
        $this->assertDirectoryDoesNotExist("{$this->modules}/weather");
    }

    public function test_a_package_can_be_copied_without_composer(): void
    {
        Process::fake(["'composer' *" => Process::result()]);
        $url = $this->repository($this->source('jalaops-module-weather', [
            'composer.json' => '{"name": "jalaops/weather", "type": "jalaops-module"}',
        ]), ['v1.0.0']);

        $this->artisan('modules:add', ['source' => $url, '--copy' => true])
            ->expectsOutputToContain("It's the Composer package jalaops/weather, copied without Composer")
            ->expectsOutputToContain("Added module 'weather' (Sää) to")
            ->assertExitCode(0);

        Process::assertNotRan(fn (PendingProcess $process) => ($process->command[0] ?? null) === 'composer');
        $this->assertFileExists("{$this->modules}/weather/composer.json");
        // It works at once, from the modules folder.
        $this->assertSame('Sää', app(ModuleRegistry::class)->find('weather')->name);
    }

    public function test_a_failed_composer_require_takes_the_repository_back_out(): void
    {
        Process::fake([
            "'composer' 'require' *" => Process::result(errorOutput: 'Your requirements could not be resolved.', exitCode: 1),
            "'composer' *" => Process::result(),
        ]);
        $url = $this->repository($this->source('jalaops-module-weather', [
            'composer.json' => '{"name": "jalaops/weather", "type": "jalaops-module"}',
        ]), ['v1.0.0']);

        $this->artisan('modules:add', ['source' => $url])
            ->expectsOutputToContain('composer require failed: Your requirements could not be resolved.')
            ->assertExitCode(1);

        Process::assertRan(fn (PendingProcess $process) => $process->command === ['composer', 'config', '--unset', 'repositories.jalaops-module-weather', '--no-interaction']);
    }

    public function test_a_package_needs_a_version_tag(): void
    {
        $url = $this->repository($this->source('jalaops-module-weather', [
            'composer.json' => '{"name": "jalaops/weather", "type": "jalaops-module"}',
        ]));

        $this->artisan('modules:add', ['source' => $url])
            ->expectsOutputToContain('the repository has none. Tag a version first.')
            ->assertExitCode(1);
    }

    public function test_it_removes_a_module_and_with_purge_its_data(): void
    {
        $this->artisan('modules:add', ['source' => $this->source()])->assertExitCode(0);
        File::ensureDirectoryExists("{$this->storage}/weather");
        ModuleSetting::query()->create(['module' => 'weather', 'enabled' => true]);
        ModuleWidgetSetting::query()->create(['widget' => 'weather.today', 'hook' => 'dashboard.top']);
        ModuleWidgetSetting::query()->create(['widget' => 'weatherman.today', 'hook' => 'dashboard.top']);

        $this->artisan('modules:remove', ['module' => 'weather'])
            ->expectsConfirmation("Remove module 'weather' (its folder)?", 'no')
            ->expectsOutput('Nothing was removed.')
            ->assertExitCode(1);
        $this->assertDirectoryExists("{$this->modules}/weather");

        $this->artisan('modules:remove', ['module' => 'weather', '--purge' => true])
            ->expectsConfirmation("Remove module 'weather' (its folder) and delete its data and settings?", 'yes')
            ->expectsOutputToContain("Deleted {$this->modules}/weather.")
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist("{$this->modules}/weather");
        $this->assertDirectoryDoesNotExist("{$this->storage}/weather");
        $this->assertNull(ModuleSetting::find('weather'));
        $this->assertSame(['weatherman.today'], ModuleWidgetSetting::query()->pluck('widget')->all());
    }

    public function test_it_removes_a_package_with_composer(): void
    {
        Process::fake(["'composer' *" => Process::result()]);
        app()->instance(ModuleRegistry::class, new ModuleRegistry($this->modules, ['jalaops/weather' => $this->source()]));

        $this->artisan('modules:remove', ['module' => 'weather', '--force' => true])
            ->expectsOutputToContain('Removed the package jalaops/weather')
            ->assertExitCode(0);

        Process::assertRan(fn (PendingProcess $process) => $process->command === ['composer', 'remove', 'jalaops/weather', '--no-interaction']);
    }

    public function test_unknown_modules_and_sources_are_reported(): void
    {
        $this->artisan('modules:remove', ['module' => 'nope', '--force' => true])
            ->expectsOutput("Module 'nope' was not found. modules:list lists them.")
            ->assertExitCode(1);

        $this->artisan('modules:add', ['source' => 'no-such-folder'])
            ->expectsOutputToContain("'no-such-folder' is not a folder, an archive")
            ->assertExitCode(1);

        $this->artisan('modules:add', ['source' => $this->source(), '--ref' => 'v1.0.0'])
            ->expectsOutputToContain('--ref is only for git URLs.')
            ->assertExitCode(1);
    }

    public function test_production_asks_first(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('modules:add', ['source' => $this->source()])
            ->expectsConfirmation('This is a production server. A module added here is lost when the server is redeployed or restarted (on Cloud Run, every instance has its own temporary files), and it skips code review. Add it through a pull request instead. Add it here anyway?', 'no')
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist("{$this->modules}/weather");
    }
}
