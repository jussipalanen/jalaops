<?php

namespace Tests\Feature;

use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Module routes: addresses of their own for a module's pages (/notes/:note).
 * Uses tests/Fixtures/modules.
 */
class ModuleRoutesTest extends TestCase
{
    use RefreshDatabase;

    private string $modules;

    protected function setUp(): void
    {
        parent::setUp();

        config(['modules.path' => base_path('tests/Fixtures/modules')]);
    }

    protected function tearDown(): void
    {
        if (isset($this->modules)) {
            File::deleteDirectory($this->modules);
        }

        parent::tearDown();
    }

    /**
     * A modules folder with one module whose module.json has these routes.
     */
    private function moduleWithRoutes(mixed $routes): ModuleRegistry
    {
        $this->modules = sys_get_temp_dir().'/jalaops-module-routes-'.uniqid();
        File::ensureDirectoryExists("{$this->modules}/notes");
        File::put("{$this->modules}/notes/main.py", 'print("{}")');
        File::put("{$this->modules}/notes/module.json", json_encode(['name' => 'Notes', 'routes' => $routes]));
        config(['modules.path' => $this->modules]);

        return app(ModuleRegistry::class);
    }

    public function test_a_module_can_have_routes_with_fixed_parameters(): void
    {
        $this->assertSame([
            ['path' => '/notes', 'params' => []],
            ['path' => '/notes/new', 'params' => ['view' => 'new']],
            ['path' => '/notes/:note', 'params' => []],
            ['path' => '/notes/:note/edit', 'params' => ['view' => 'edit']],
        ], app(ModuleRegistry::class)->find('routes')->routes);
    }

    public function test_modules_without_routes_have_none(): void
    {
        $this->assertSame([], app(ModuleRegistry::class)->find('disabled')->routes);
    }

    public function test_the_api_sends_the_routes(): void
    {
        $module = collect($this->getJson('/api/modules')->assertOk()->json('data'))->firstWhere('id', 'routes');

        $this->assertSame('/notes/:note/edit', $module['routes'][3]['path']);
        $response = $this->getJson('/api/modules/routes')->assertOk()->assertJsonPath('data.routes.1.params.view', 'new');

        // Empty params are an object, not a list.
        $this->assertInstanceOf(\stdClass::class, json_decode($response->getContent())->data->routes[0]->params);
    }

    public function test_the_first_module_by_id_keeps_an_address(): void
    {
        $modules = app(ModuleRegistry::class)->all();

        $this->assertCount(4, $modules['routes']->routes);
        // routes-clash uses /notes too, so it loses all its routes but still works.
        $this->assertSame([], $modules['routes-clash']->routes);
        $this->assertSame([], app(ModuleRegistry::class)->find('routes-clash')->routes);
        $this->getJson('/api/modules/routes-clash')->assertOk()->assertJsonPath('data.routes', []);
    }

    public function test_a_route_cant_use_the_apps_own_addresses(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage("can't start with /tools");

        app(ModuleRegistry::class)->find('bad-route');
    }

    public function test_broken_routes_make_the_module_broken(): void
    {
        foreach ([
            'not a list' => ['path' => '/notes'],
            'no path' => [['params' => []]],
            'no leading slash' => [['path' => 'notes']],
            'a parameter first' => [['path' => '/:note']],
            'capital letters' => [['path' => '/Notes']],
            'a trailing slash' => [['path' => '/notes/']],
            'a bad parameter name' => [['path' => '/notes/:Note']],
            'too many parts' => [['path' => '/a/b/c/d/e/f/g']],
            'the api' => [['path' => '/api/notes']],
            'a parameter twice' => [['path' => '/notes/:note/:note']],
            'a parameter also fixed' => [['path' => '/notes/:view', 'params' => ['view' => 'edit']]],
            'params as a list' => [['path' => '/notes', 'params' => ['edit']]],
            'a number as a param' => [['path' => '/notes', 'params' => ['page' => 2]]],
            'the same address twice' => [['path' => '/notes/:note'], ['path' => '/notes/:id']],
            'too many routes' => array_map(fn ($n) => ['path' => "/notes/{$n}"], range(1, 11)),
        ] as $case => $routes) {
            try {
                $this->moduleWithRoutes($routes)->find('notes');
                $this->fail("The routes should be rejected: {$case}.");
            } catch (ModuleException $exception) {
                $this->assertStringContainsString("Module 'notes'", $exception->getMessage(), $case);
            }
        }
    }
}
