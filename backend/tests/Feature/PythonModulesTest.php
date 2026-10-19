<?php

namespace Tests\Feature;

use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PythonModulesTest extends TestCase
{
    use RefreshDatabase;

    private function runModule(string $module, array $input = []): array
    {
        return app(ModuleRunner::class)->run(app(ModuleRegistry::class)->find($module), $input);
    }

    /**
     * Uses the misbehaving test modules in tests/Fixtures/modules.
     */
    private function useFixtures(): void
    {
        config(['modules.path' => base_path('tests/Fixtures/modules')]);
    }

    public function test_hello_greets_and_calculates(): void
    {
        $output = $this->runModule('hello', ['name' => 'Jussi', 'numbers' => [3, 5, 10]]);

        $this->assertSame('Hei, Jussi!', $output['message']);
        $this->assertSame(3, $output['count']);
        $this->assertSame(18, $output['sum']);
        $this->assertEquals(6, $output['average']);
        $this->assertEquals(5, $output['median']);
        $this->assertMatchesRegularExpression('/^3\.\d+/', $output['python']);
    }

    public function test_hello_works_without_input(): void
    {
        $output = $this->runModule('hello');

        $this->assertSame('Hei, Maailma!', $output['message']);
        $this->assertSame(0, $output['count']);
        $this->assertArrayNotHasKey('sum', $output);
    }

    public function test_hello_rejects_numbers_that_are_not_numbers(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Lukujen pitää olla lista numeroita.');

        $this->runModule('hello', ['numbers' => [1, 'kaksi', true]]);
    }

    public function test_the_vat_calculator_rounds_to_cents(): void
    {
        $this->assertSame(['net' => 100.0, 'rate' => 25.5, 'vat' => 25.5, 'total' => 125.5], $this->runModule('vat-calculator', ['amount' => 100]));
        $this->assertSame(2.8, $this->runModule('vat-calculator', ['amount' => 19.99, 'rate' => '14'])['vat']);
        $this->assertSame(0.0, $this->runModule('vat-calculator', ['amount' => 50, 'rate' => '0'])['vat']);
    }

    public function test_the_vat_calculator_checks_its_input(): void
    {
        foreach ([
            [['amount' => 'sata'], 'Anna veroton hinta numerona.'],
            [['amount' => 100, 'rate' => '24'], 'ALV-kannan pitää olla 25.5, 14, 10 tai 0 %.'],
            [['amount' => -1], 'Verottoman hinnan pitää olla 0–1 000 000 000 €.'],
        ] as [$input, $reason]) {
            try {
                $this->runModule('vat-calculator', $input);
                $this->fail('The input should be rejected: '.json_encode($input));
            } catch (ModuleException $exception) {
                $this->assertSame($reason, $exception->reason);
            }
        }
    }

    public function test_the_registry_finds_the_modules(): void
    {
        $module = app(ModuleRegistry::class)->all()['hello'];

        $this->assertSame('Hei maailma', $module->name);
        $this->assertSame('1.0.0', $module->version);
        $this->assertSame(5, $module->timeout);
        $this->assertTrue($module->enabled);
        $this->assertStringEndsWith('modules/hello/main.py', $module->entry);
        $this->assertSame(['name', 'numbers'], array_column($module->form, 'name'));
        $this->assertSame('numbers', $module->form[1]['type']);
        $this->assertContains(['name' => 'sum', 'label' => 'Summa'], $module->results);
    }

    public function test_a_module_is_off_unless_its_module_json_turns_it_on(): void
    {
        $this->useFixtures();

        $this->assertFalse(app(ModuleRegistry::class)->find('disabled')->enabled);
        $this->assertTrue(app(ModuleRegistry::class)->find('environment')->enabled);
    }

    public function test_modules_can_be_turned_off_and_on(): void
    {
        $registry = app(ModuleRegistry::class);

        $this->assertFalse($registry->setEnabled('hello', false)->enabled);
        $this->assertFalse($registry->find('hello')->enabled);
        $this->assertFalse($registry->all()['hello']->enabled);
        $this->assertDatabaseHas('module_settings', ['module' => 'hello', 'enabled' => false]);

        $this->assertTrue($registry->setEnabled('hello', true)->enabled);
    }

    public function test_a_disabled_module_does_not_run(): void
    {
        app(ModuleRegistry::class)->setEnabled('hello', false);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage("Module 'hello' is disabled.");

        $this->runModule('hello');
    }

    public function test_a_module_can_have_its_own_view(): void
    {
        $this->assertStringEndsWith('modules/hello/view.html', app(ModuleRegistry::class)->find('hello')->view);

        $this->useFixtures();
        $this->assertNull(app(ModuleRegistry::class)->find('environment')->view);
    }

    public function test_a_view_can_use_css_and_js_files(): void
    {
        $assets = app(ModuleRegistry::class)->find('hello')->assets;

        $this->assertSame(['style', 'script'], array_column($assets, 'type'));
        $this->assertStringEndsWith('modules/hello/assets/chart.js', $assets[1]['path']);
    }

    public function test_assets_must_be_css_or_js_files_inside_the_modules_folder(): void
    {
        $this->useFixtures();

        foreach (['bad-asset-type', 'outside-asset'] as $module) {
            try {
                app(ModuleRegistry::class)->find($module);
                $this->fail("Module '{$module}' should be rejected.");
            } catch (ModuleException $exception) {
                $this->assertStringContainsString("each asset must be a .css or .js file inside the module's folder", $exception->getMessage());
            }
        }
    }

    public function test_the_view_must_be_inside_the_modules_folder(): void
    {
        $this->useFixtures();

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage("the view must be an HTML file inside the module's folder");

        app(ModuleRegistry::class)->find('outside-view');
    }

    public function test_a_form_field_must_have_a_known_type(): void
    {
        $this->useFixtures();

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('each form field needs a name, a label and a type');

        app(ModuleRegistry::class)->find('bad-form');
    }

    public function test_the_registry_skips_broken_modules(): void
    {
        $this->useFixtures();

        $modules = app(ModuleRegistry::class)->all();

        $this->assertArrayHasKey('environment', $modules);
        $this->assertArrayNotHasKey('broken', $modules);
        $this->assertArrayNotHasKey('escape', $modules);
        $this->assertArrayNotHasKey('bad-form', $modules);
    }

    public function test_unknown_modules_and_bad_ids_are_not_found(): void
    {
        foreach (['nope', '../modules/hello', 'Hello', ''] as $id) {
            try {
                app(ModuleRegistry::class)->find($id);
                $this->fail("Module id '{$id}' should not be found.");
            } catch (ModuleException $exception) {
                $this->assertStringContainsString('was not found', $exception->getMessage());
            }
        }
    }

    public function test_the_entry_script_must_stay_in_the_modules_folder(): void
    {
        $this->useFixtures();

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage("the entry script must be a file inside the module's folder");

        app(ModuleRegistry::class)->find('escape');
    }

    public function test_a_module_does_not_get_the_backends_secrets(): void
    {
        $this->useFixtures();
        putenv('GEMINI_API_KEY=secret-key');
        $_ENV['DB_PASSWORD'] = 'secret-password';

        try {
            $variables = $this->runModule('environment')['variables'];
        } finally {
            putenv('GEMINI_API_KEY');
            unset($_ENV['DB_PASSWORD']);
        }

        $this->assertContains('PATH', $variables);
        $this->assertNotContains('GEMINI_API_KEY', $variables);
        $this->assertNotContains('DB_PASSWORD', $variables);
        $this->assertNotContains('APP_KEY', $variables);
    }

    public function test_a_failing_module_reports_its_exit_code_and_error(): void
    {
        $this->useFixtures();

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage("Module 'failing' failed with exit code 3: Jokin meni pieleen.");

        try {
            $this->runModule('failing');
        } catch (ModuleException $exception) {
            $this->assertSame('Jokin meni pieleen.', $exception->reason);

            throw $exception;
        }
    }

    public function test_a_module_must_print_a_json_object(): void
    {
        $this->useFixtures();

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage("Module 'not-json' must print a JSON object.");

        $this->runModule('not-json');
    }

    public function test_a_slow_module_is_stopped(): void
    {
        $this->useFixtures();

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage("Module 'slow' took longer than 1 s and was stopped.");

        $this->runModule('slow');
    }

    public function test_the_list_command_shows_the_modules(): void
    {
        $this->artisan('modules:list')
            ->expectsTable(['Module', 'Name', 'Version', 'Status', 'Description'], [
                ['hello', 'Hei maailma', '1.0.0', 'on', 'Esimerkkimoduuli: tervehtii ja laskee lukujen summan, keskiarvon ja mediaanin.'],
                ['vat-calculator', 'ALV-laskuri', '1.0.0', 'on', 'Laskee verottoman hinnan, arvonlisäveron ja verollisen hinnan.'],
            ])
            ->assertSuccessful();
    }

    public function test_the_run_command_prints_the_output(): void
    {
        $exitCode = Artisan::call('modules:run', ['module' => 'hello', 'input' => '{"name": "Jussi", "numbers": [1, 2]}']);

        $this->assertSame(0, $exitCode);
        $this->assertSame(['message' => 'Hei, Jussi!', 'count' => 2, 'sum' => 3], array_intersect_key(
            json_decode(Artisan::output(), true),
            array_flip(['message', 'count', 'sum']),
        ));
    }

    public function test_the_run_command_needs_a_json_object(): void
    {
        foreach (['not json', '[1, 2]'] as $input) {
            $this->artisan('modules:run', ['module' => 'hello', 'input' => $input])
                ->expectsOutputToContain('The input must be a JSON object')
                ->assertFailed();
        }
    }

    public function test_the_run_command_reports_module_errors(): void
    {
        $this->artisan('modules:run', ['module' => 'nope'])
            ->expectsOutputToContain("Module 'nope' was not found")
            ->assertFailed();
    }

    public function test_the_enable_and_disable_commands_switch_a_module(): void
    {
        $this->artisan('modules:disable', ['module' => 'hello'])
            ->expectsOutput("Module 'hello' is now off.")
            ->assertSuccessful();
        $this->assertFalse(app(ModuleRegistry::class)->find('hello')->enabled);

        $this->artisan('modules:enable', ['module' => 'hello'])
            ->expectsOutput("Module 'hello' is now on.")
            ->assertSuccessful();
        $this->assertTrue(app(ModuleRegistry::class)->find('hello')->enabled);

        $this->artisan('modules:enable', ['module' => 'nope'])->assertFailed();
    }
}
