<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_app_details_and_health(): void
    {
        $this->getJson('/api/system')
            ->assertOk()
            ->assertJsonPath('app.name', 'JalaOps')
            ->assertJsonPath('app.api_version', config('scramble.info.version'))
            ->assertJsonPath('app.environment', 'testing')
            ->assertJsonPath('app.locale', 'fi')
            ->assertJsonPath('health.database.status', 'ok')
            ->assertJsonPath('health.cache.status', 'ok')
            ->assertJsonStructure(['app' => ['demo', 'timezone', 'server_time'], 'health' => ['database' => ['response_ms']]]);
    }

    public function test_hides_versions_unless_enabled(): void
    {
        config(['app.show_system_details' => false]);

        $this->getJson('/api/system')->assertOk()->assertJsonPath('details', null);
    }

    public function test_shows_versions_when_enabled(): void
    {
        config(['app.show_system_details' => true]);

        $this->getJson('/api/system')
            ->assertOk()
            ->assertJsonPath('details.php', PHP_VERSION)
            ->assertJsonPath('details.laravel', app()->version())
            ->assertJsonPath('details.database.driver', 'sqlite')
            ->assertJsonPath('details.cache_driver', 'array');
    }
}
