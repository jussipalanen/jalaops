<?php

namespace Tests\Feature;

use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The Python module SDK in modules/sdk (jalaops_module.py). Uses the sdk
 * module in tests/Fixtures/modules.
 */
class ModuleSdkTest extends TestCase
{
    use RefreshDatabase;

    private function runModule(array $input = []): array
    {
        config(['modules.path' => base_path('tests/Fixtures/modules')]);

        return app(ModuleRunner::class)->run(app(ModuleRegistry::class)->find('sdk'), $input);
    }

    private function reason(array $input): string
    {
        try {
            $this->runModule($input);
        } catch (ModuleException $exception) {
            return $exception->reason;
        }

        $this->fail('The module should fail: '.json_encode($input));
    }

    public function test_the_sdks_own_tests_pass(): void
    {
        $result = Process::path(config('modules.sdk'))->run([config('modules.python'), '-B', '-m', 'unittest']);

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertStringContainsString('OK', $result->errorOutput());
    }

    public function test_a_module_anywhere_can_use_the_sdk(): void
    {
        $this->assertSame(['items' => [0, 1]], $this->runModule());
        $this->assertSame(['items' => [0, 1, 2]], $this->runModule(['action' => 'list', 'limit' => 3]));
        $this->assertSame(['id' => 4], $this->runModule(['action' => 'get', 'id' => 4]));
    }

    public function test_its_errors_reach_the_user_in_finnish(): void
    {
        $this->assertSame('Ei löytynyt.', $this->reason(['action' => 'get', 'id' => 10]));
        $this->assertSame('Numero: anna kokonaisluku.', $this->reason(['action' => 'get', 'id' => 'neljä']));
        $this->assertSame('Numero: pakollinen tieto.', $this->reason(['action' => 'get']));
        $this->assertSame('Tuntematon toiminto: delete. Sallitut: list, get, crash.', $this->reason(['action' => 'delete']));
    }

    public function test_a_crash_shows_a_generic_message_and_logs_the_details(): void
    {
        try {
            $this->runModule(['action' => 'crash']);
            $this->fail('The module should fail.');
        } catch (ModuleException $exception) {
            $this->assertSame('Moduulissa tapahtui virhe.', $exception->reason);
            $this->assertStringContainsString('RuntimeError: secret detail', $exception->getMessage());
        }
    }
}
