<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AllowedOriginsTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://jalaops.example';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai_overview.enabled' => true,
            'ai_overview.provider' => 'gemini',
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-test',
            'cors.allowed_origins' => [self::SITE, 'http://localhost:5173'],
        ]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['text' => '{"summary": "Kaikki hyvin.", "actions": [], "risks": []}']]],
                'finishReason' => 'STOP',
            ]],
        ])]);
    }

    public function test_the_ai_overview_works_from_the_apps_own_pages(): void
    {
        $this->withHeader('Origin', self::SITE)
            ->postJson('/api/dashboard/ai-overview/refresh')
            ->assertOk()
            ->assertJsonPath('summary', 'Kaikki hyvin.');

        $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/dashboard/ai-overview')
            ->assertOk();
    }

    public function test_a_same_origin_get_is_recognised_by_its_referer(): void
    {
        // Browsers send no Origin header with a same-origin GET.
        $this->withHeader('Referer', self::SITE.'/')
            ->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertJsonPath('enabled', true);
    }

    public function test_the_ai_overview_rejects_other_websites(): void
    {
        $this->withHeader('Origin', 'https://evil.example')
            ->postJson('/api/dashboard/ai-overview/refresh')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tätä toimintoa voi käyttää vain JalaOpsin omalla sivustolla.');

        $this->withHeader('Referer', 'https://jalaops.example.evil.example/')
            ->getJson('/api/dashboard/ai-overview')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_the_origin_header_wins_over_the_referer(): void
    {
        $this->withHeaders(['Origin' => 'null', 'Referer' => self::SITE.'/'])
            ->postJson('/api/dashboard/ai-overview/refresh')
            ->assertForbidden();
    }

    public function test_the_ai_overview_rejects_requests_without_a_page(): void
    {
        $this->getJson('/api/dashboard/ai-overview')->assertForbidden();
        $this->postJson('/api/dashboard/ai-overview/refresh')->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_any_origin_is_allowed_in_local_development(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        // E.g. a phone on the local network at http://192.168.1.20:5173.
        $this->withHeader('Origin', 'http://192.168.1.20:5173')
            ->getJson('/api/dashboard/ai-overview')
            ->assertOk();
    }

    public function test_cors_allows_only_the_apps_own_pages(): void
    {
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/dashboard/ai-overview/refresh', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $preflight(self::SITE)->assertHeader('Access-Control-Allow-Origin', self::SITE);
        $preflight('https://evil.example')->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_other_api_routes_still_answer_requests_without_a_page(): void
    {
        $this->getJson('/api/requests')->assertOk();
    }
}
