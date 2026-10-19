<?php

namespace Tests\Feature;

use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
use App\Services\Modules\ModuleWidgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Module data folders, larger inputs, widgets in the dashboard's hooks, and
 * turning modules on and off in the app. Uses tests/Fixtures/modules.
 */
class ModuleWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://jalaops.example';

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir().'/jalaops-module-storage-'.uniqid();

        config([
            'cors.allowed_origins' => [self::SITE],
            'modules.path' => base_path('tests/Fixtures/modules'),
            'modules.storage' => $this->storage,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    private function runModule(string $module, array $input = []): array
    {
        return app(ModuleRunner::class)->run(app(ModuleRegistry::class)->find($module), $input);
    }

    public function test_a_module_with_storage_gets_a_data_folder_of_its_own(): void
    {
        $first = $this->runModule('storage');
        $second = $this->runModule('storage');

        $this->assertSame("{$this->storage}/storage", $first['directory']);
        $this->assertSame([1, 2], [$first['count'], $second['count']]);
        $this->assertFileExists("{$this->storage}/storage/count.txt");
    }

    public function test_other_modules_get_no_data_folder(): void
    {
        $variables = $this->runModule('environment')['variables'];

        $this->assertNotContains('JALAOPS_DATA_DIR', $variables);
        $this->assertDirectoryDoesNotExist("{$this->storage}/environment");
    }

    public function test_a_module_can_take_a_larger_input(): void
    {
        $this->withHeader('Origin', self::SITE);

        $this->postJson('/api/modules/storage/run', ['text' => str_repeat('a', 15_000)])
            ->assertOk()
            ->assertJsonPath('output.size', 15_000);

        $this->postJson('/api/modules/storage/run', ['text' => str_repeat('a', 21_000)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Syötteen pitää olla JSON-objekti, enintään 20 kt.');

        $this->postJson('/api/modules/environment/run', ['text' => str_repeat('a', 11_000)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Syötteen pitää olla JSON-objekti, enintään 10 kt.');
    }

    public function test_the_module_gets_the_input_as_it_was_sent(): void
    {
        $this->withHeader('Origin', self::SITE);

        // Not trimmed, and empty strings stay strings.
        $this->postJson('/api/modules/storage/run', ['text' => '  a  '])->assertOk()->assertJsonPath('output.text', '  a  ');
        $this->postJson('/api/modules/storage/run', ['text' => ''])->assertOk()->assertJsonPath('output.text', '');

        $this->call('POST', '/api/modules/storage/run', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::SITE], '{not json')
            ->assertUnprocessable();
    }

    public function test_the_dashboard_gets_the_hooks_and_the_widgets_with_their_items(): void
    {
        $response = $this->withHeader('Origin', self::SITE)->getJson('/api/dashboard/widgets')->assertOk();

        $response->assertJsonPath('hooks.0', ['id' => 'dashboard.top', 'label' => 'Etusivun yläosa']);
        $this->assertSame(['failing-widget.feed', 'storage.latest'], array_column($response->json('data'), 'id'));

        $response->assertJsonPath('data.1', [
            'id' => 'storage.latest',
            'module' => 'storage',
            'module_name' => 'Storage',
            'routes' => [['path' => '/stored/:news', 'params' => []]],
            'title' => 'Viimeisimmät',
            'type' => 'feed',
            'hook' => 'dashboard.top',
            'items' => [
                // Only the known keys pass, the image only as a data URL, and
                // params only with simple names and values.
                [
                    'title' => 'Ensimmäinen',
                    'text' => 'Teksti',
                    'date' => '2026-10-19T12:00:00Z',
                    'image' => 'data:image/png;base64,iVBORw0KGgo=',
                    'tags' => ['a'],
                    'params' => ['news' => '3'],
                ],
                ['title' => 'Toinen', 'text' => null, 'date' => null, 'image' => null, 'tags' => [], 'params' => []],
            ],
            'error' => null,
        ]);
    }

    public function test_a_failing_widget_shows_an_error(): void
    {
        $this->withHeader('Origin', self::SITE)
            ->getJson('/api/dashboard/widgets')
            ->assertJsonPath('data.0.items', [])
            ->assertJsonPath('data.0.error', 'Sisällön lataaminen epäonnistui.');
    }

    public function test_widgets_of_modules_that_are_off_are_not_shown(): void
    {
        app(ModuleRegistry::class)->setEnabled('storage', false);

        $response = $this->withHeader('Origin', self::SITE)->getJson('/api/dashboard/widgets');

        $this->assertSame(['failing-widget.feed'], array_column($response->json('data'), 'id'));
    }

    public function test_only_the_apps_own_pages_get_the_widgets(): void
    {
        $this->withHeader('Origin', 'https://evil.example')->getJson('/api/dashboard/widgets')->assertForbidden();
    }

    public function test_a_widget_needs_a_known_hook(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('each widget needs a name');

        app(ModuleRegistry::class)->find('bad-widget');
    }

    public function test_the_place_command_moves_and_hides_a_widget_for_everyone(): void
    {
        $this->artisan('modules:place', ['widget' => 'storage.latest', 'hook' => 'dashboard.bottom'])
            ->expectsOutput("Widget 'storage.latest' is now shown in dashboard.bottom.")
            ->assertSuccessful();

        $this->withHeader('Origin', self::SITE)->getJson('/api/dashboard/widgets')->assertJsonPath('data.1.hook', 'dashboard.bottom');

        $this->artisan('modules:place', ['widget' => 'storage.latest', 'hook' => 'hidden'])
            ->expectsOutput("Widget 'storage.latest' is now hidden.")
            ->assertSuccessful();

        $this->assertSame(['failing-widget.feed'], array_column($this->getJson('/api/dashboard/widgets')->json('data'), 'id'));

        $this->artisan('modules:place', ['widget' => 'storage.latest', 'hook' => 'default'])->assertSuccessful();

        $this->assertSame('dashboard.top', collect(app(ModuleWidgets::class)->all())->firstWhere('id', 'storage.latest')['hook']);
    }

    public function test_the_place_command_checks_the_widget_and_the_hook(): void
    {
        $this->artisan('modules:place', ['widget' => 'nope.latest', 'hook' => 'dashboard.top'])
            ->expectsOutputToContain("Widget 'nope.latest' was not found.")
            ->assertFailed();

        $this->artisan('modules:place', ['widget' => 'storage.latest', 'hook' => 'sidebar'])
            ->expectsOutputToContain("Unknown place 'sidebar'.")
            ->assertFailed();
    }

    public function test_the_widgets_command_lists_the_widgets(): void
    {
        $this->artisan('modules:widgets')
            ->expectsTable(['Widget', 'Title', 'Hook', 'Status'], [
                ['failing-widget.feed', 'Rikki', 'dashboard.bottom', 'on'],
                ['storage.latest', 'Viimeisimmät', 'dashboard.top', 'on'],
            ])
            ->assertSuccessful();
    }

    public function test_users_turn_modules_on_and_off_in_the_app(): void
    {
        $this->withHeader('Origin', self::SITE);

        $this->patchJson('/api/modules/storage', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.id', 'storage')
            ->assertJsonPath('data.enabled', false);

        $this->postJson('/api/modules/storage/run')->assertForbidden();

        $this->patchJson('/api/modules/disabled', ['enabled' => true])->assertOk()->assertJsonPath('data.enabled', true);
        $this->assertTrue(app(ModuleRegistry::class)->find('disabled')->enabled);
    }

    public function test_turning_a_module_on_or_off_is_checked(): void
    {
        $this->withHeader('Origin', self::SITE);

        $this->patchJson('/api/modules/storage', [])->assertUnprocessable()->assertJsonValidationErrors('enabled');
        $this->patchJson('/api/modules/nope', ['enabled' => true])->assertNotFound();

        $this->withHeader('Origin', 'https://evil.example')->patchJson('/api/modules/storage', ['enabled' => false])->assertForbidden();
    }
}
