<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'database' => 'ok', 'demo' => false]);
    }

    public function test_health_endpoint_reports_database_error(): void
    {
        Config::set('database.connections.sqlite.database', '/nonexistent/database.sqlite');
        DB::purge('sqlite');

        $this->getJson('/api/health')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'error', 'database' => 'error', 'demo' => false]);
    }

    public function test_health_endpoint_reports_demo_mode(): void
    {
        Config::set('app.demo_mode', true);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('demo', true);
    }
}
