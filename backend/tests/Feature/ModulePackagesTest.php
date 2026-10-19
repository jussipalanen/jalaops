<?php

namespace Tests\Feature;

use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Modules from Composer packages of type "jalaops-module", using the test
 * packages in tests/Fixtures/packages next to the app's own modules.
 */
class ModulePackagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ModuleRegistry::class, new ModuleRegistry(base_path('modules'), [
            'acme/toolbox' => base_path('tests/Fixtures/packages/toolbox'),
            'acme/weather' => base_path('tests/Fixtures/packages/weather'),
        ]));
    }

    public function test_installed_packages_can_be_left_out(): void
    {
        config(['modules.packages' => false]);

        $this->assertSame(['hello', 'vat-calculator'], array_keys(ModuleRegistry::fromConfig()->all()));
    }

    public function test_only_installed_packages_of_the_module_type_are_used(): void
    {
        $installed = [
            ['versions' => [
                'laravel/framework' => ['type' => 'library', 'install_path' => '/app/vendor/laravel/framework'],
                'acme/weather' => ['type' => 'jalaops-module', 'install_path' => '/app/vendor/acme/weather'],
                // Provided by another package, so not installed on its own.
                'acme/virtual' => ['type' => 'jalaops-module', 'provided' => ['1.0']],
            ]],
            // Another autoloader's data; the first install path wins.
            ['versions' => [
                'acme/weather' => ['type' => 'jalaops-module', 'install_path' => '/other/vendor/acme/weather'],
                'acme/toolbox' => ['type' => 'jalaops-module', 'install_path' => '/app/vendor/acme/toolbox'],
            ]],
        ];

        $this->assertSame([
            'acme/toolbox' => '/app/vendor/acme/toolbox',
            'acme/weather' => '/app/vendor/acme/weather',
        ], ModuleRegistry::installedPackages($installed));
    }

    public function test_a_package_with_a_module_json_is_one_module_named_after_it(): void
    {
        $module = app(ModuleRegistry::class)->find('weather');

        $this->assertSame('Sää', $module->name);
        $this->assertSame('acme/weather', $module->package);
        $this->assertStringEndsWith('packages/weather', $module->directory);
        $this->assertSame(['city' => 'Tampere', 'forecast' => 'Aurinkoista'], app(ModuleRunner::class)->run($module, ['city' => 'Tampere']));
    }

    public function test_a_package_can_hold_a_folder_of_modules(): void
    {
        $module = app(ModuleRegistry::class)->find('counter');

        $this->assertSame('acme/toolbox', $module->package);
        $this->assertSame(['count' => 2], app(ModuleRunner::class)->run($module, ['a' => 1, 'b' => 2]));
    }

    public function test_the_modules_folder_wins_when_ids_collide(): void
    {
        Log::spy();

        $modules = app(ModuleRegistry::class)->all();

        $this->assertSame(['counter', 'hello', 'vat-calculator', 'weather'], array_keys($modules));
        $this->assertNull($modules['hello']->package);
        $this->assertSame('Hei maailma', $modules['hello']->name);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, "Module 'hello'")
            && str_contains($message, 'packages/toolbox/hello was skipped'));
    }

    public function test_package_modules_can_be_turned_off_and_on(): void
    {
        $registry = app(ModuleRegistry::class);

        $this->assertFalse($registry->setEnabled('weather', false)->enabled);
        $this->assertFalse($registry->all()['weather']->enabled);
        $this->assertTrue($registry->setEnabled('weather', true)->enabled);
    }

    public function test_the_api_lists_package_modules_with_the_others(): void
    {
        $this->getJson('/api/modules')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.3', [
                'id' => 'weather',
                'name' => 'Sää',
                'description' => '',
                'version' => '2.1.0',
                'enabled' => true,
                'routes' => [],
            ]);
    }

    public function test_the_list_command_shows_where_each_module_comes_from(): void
    {
        $this->artisan('modules:list')
            ->expectsOutputToContain('acme/toolbox')
            ->expectsOutputToContain('acme/weather')
            ->assertSuccessful();
    }
}
