<?php

namespace Tests\Feature;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Services\AiStatusOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_URL = 'generativelanguage.googleapis.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-09 12:00:00');

        config([
            'ai_overview.enabled' => true,
            'ai_overview.provider' => 'gemini',
            'ai_overview.cache_minutes' => 60,
            'ai_overview.max_generations_per_hour' => 20,
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-test',
            'services.puter.auth_token' => null,
            'services.puter.model' => 'puter-test',
        ]);
    }

    /**
     * A Gemini generateContent reply whose text is the given value as JSON.
     */
    private function geminiReply(array $overview, bool $fenced = false): array
    {
        $json = json_encode($overview, JSON_UNESCAPED_UNICODE);

        return [
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => [
                        ['text' => 'Pohditaan…', 'thought' => true],
                        ['text' => $fenced ? "```json\n{$json}\n```" : $json],
                    ],
                ],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    private function fakeGemini(?array $overview = null): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiReply($overview ?? [
            'summary' => 'Avoimia pyyntöjä on 2, joista yksi on myöhässä.',
            'actions' => ['Hoida myöhässä oleva pyyntö ensin.'],
            'risks' => [],
        ]))]);
    }

    public function test_it_is_disabled_by_default(): void
    {
        config(['ai_overview.enabled' => false]);
        Http::fake();

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertExactJson(['enabled' => false]);

        Http::assertNothingSent();
    }

    public function test_it_is_disabled_without_an_api_key(): void
    {
        config(['services.gemini.api_key' => null]);
        Http::fake();

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertExactJson(['enabled' => false]);

        Http::assertNothingSent();
    }

    public function test_it_returns_the_overview_written_by_gemini(): void
    {
        ServiceRequest::factory()->create([
            'title' => 'Korjaa vuotava hana',
            'priority' => RequestPriority::High,
            'status' => RequestStatus::Open,
        ]);
        $this->fakeGemini();

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'summary' => 'Avoimia pyyntöjä on 2, joista yksi on myöhässä.',
                'actions' => ['Hoida myöhässä oleva pyyntö ensin.'],
                'risks' => [],
                'provider' => 'Gemini',
                'model' => 'gemini-test',
                'stale' => false,
            ])
            ->assertJsonStructure(['generated_at']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Korjaa vuotava hana');
        });
    }

    public function test_it_accepts_json_wrapped_in_a_code_block(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiReply(
            ['summary' => 'Kaikki hyvin.', 'actions' => [], 'risks' => []],
            fenced: true,
        ))]);

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertJsonPath('summary', 'Kaikki hyvin.');
    }

    public function test_it_reuses_the_cached_overview_while_the_requests_stay_the_same(): void
    {
        ServiceRequest::factory()->create();
        $this->fakeGemini();

        $this->getJson('/api/dashboard/ai-overview')->assertOk();
        $this->getJson('/api/dashboard/ai-overview')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_it_generates_a_new_overview_when_the_requests_change(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Open]);
        $this->fakeGemini();

        $this->getJson('/api/dashboard/ai-overview')->assertOk();
        $request->update(['status' => RequestStatus::InProgress]);
        $this->getJson('/api/dashboard/ai-overview')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_refresh_generates_a_new_overview(): void
    {
        $this->fakeGemini();

        $this->getJson('/api/dashboard/ai-overview')->assertOk();
        $this->postJson('/api/dashboard/ai-overview/refresh')->assertOk()->assertJsonPath('enabled', true);

        Http::assertSentCount(2);
    }

    public function test_refresh_is_throttled_per_visitor(): void
    {
        $this->fakeGemini();

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/dashboard/ai-overview/refresh')->assertOk();
        }

        $this->postJson('/api/dashboard/ai-overview/refresh')->assertTooManyRequests();
    }

    public function test_it_shows_the_previous_overview_when_the_hourly_limit_is_used_up(): void
    {
        config(['ai_overview.max_generations_per_hour' => 1]);
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Open]);
        $this->fakeGemini();

        $this->getJson('/api/dashboard/ai-overview')->assertOk()->assertJsonPath('stale', false);
        $request->update(['status' => RequestStatus::Completed]);

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertJsonPath('stale', true)
            ->assertJsonPath('summary', 'Avoimia pyyntöjä on 2, joista yksi on myöhässä.');

        Http::assertSentCount(1);
    }

    public function test_it_returns_429_when_the_limit_is_used_up_and_there_is_no_overview(): void
    {
        config(['ai_overview.max_generations_per_hour' => 0]);
        Http::fake();

        $this->getJson('/api/dashboard/ai-overview')
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'AI-tilannekatsauksia on luotu tällä tunnilla jo enimmäismäärä. Yritä myöhemmin uudelleen.');
    }

    public function test_it_returns_a_finnish_error_when_gemini_fails(): void
    {
        Http::fake([self::GEMINI_URL => Http::response(['error' => ['message' => 'Quota exceeded']], 429)]);

        $this->getJson('/api/dashboard/ai-overview')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'AI-tilannekatsauksen luominen epäonnistui. Yritä hetken kuluttua uudelleen.');
    }

    public function test_it_rejects_a_reply_without_a_summary(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiReply(['actions' => ['Tee jotain.']]))]);

        $this->getJson('/api/dashboard/ai-overview')->assertServiceUnavailable();
    }

    public function test_it_keeps_at_most_three_actions_and_risks(): void
    {
        $this->fakeGemini([
            'summary' => 'Paljon tekemistä.',
            'actions' => ['1', '2', '3', '4', 5, ''],
            'risks' => 'ei lista',
        ]);

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertJsonPath('actions', ['1', '2', '3'])
            ->assertJsonPath('risks', []);
    }

    public function test_facts_list_overdue_upcoming_and_high_priority_requests(): void
    {
        ServiceRequest::factory()->create([
            'title' => 'Myöhässä',
            'priority' => RequestPriority::High,
            'status' => RequestStatus::Open,
            'due_date' => '2026-10-06',
        ]);
        ServiceRequest::factory()->create([
            'title' => 'Ensi viikolla',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::InProgress,
            'due_date' => '2026-10-12',
        ]);
        ServiceRequest::factory()->create([
            'title' => 'Valmis ja myöhässä',
            'priority' => RequestPriority::High,
            'status' => RequestStatus::Completed,
            'due_date' => '2026-10-01',
        ]);
        ServiceRequest::factory()->create([
            'title' => 'Ei määräpäivää',
            'priority' => RequestPriority::Low,
            'status' => RequestStatus::Open,
            'due_date' => null,
        ]);

        $facts = app(AiStatusOverview::class)->facts();

        $this->assertSame('2026-10-09', $facts['today']);
        $this->assertSame(4, $facts['total']);
        $this->assertSame(['open' => 2, 'in_progress' => 1, 'completed' => 1], $facts['by_status']);
        $this->assertSame(['low' => 1, 'normal' => 1, 'high' => 1], $facts['by_priority_unfinished']);
        $this->assertSame(['Myöhässä'], array_column($facts['overdue'], 'title'));
        $this->assertSame(3, $facts['overdue'][0]['days_overdue']);
        $this->assertSame(['Ensi viikolla'], array_column($facts['due_within_7_days'], 'title'));
        $this->assertSame(['Myöhässä'], array_column($facts['high_priority_unfinished'], 'title'));
        $this->assertSame(1, $facts['unfinished_without_due_date']);
    }

    public function test_it_can_use_puter_ai_instead_of_gemini(): void
    {
        config(['ai_overview.provider' => 'puter', 'services.puter.auth_token' => 'puter-token']);
        ServiceRequest::factory()->create([
            'title' => 'Huolla trukki',
            'priority' => RequestPriority::High,
            'status' => RequestStatus::Open,
        ]);
        Http::fake(['api.puter.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode(['summary' => 'Puterin katsaus.', 'actions' => [], 'risks' => []]),
                ],
            ]],
        ])]);

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertJson(['enabled' => true, 'summary' => 'Puterin katsaus.', 'provider' => 'Puter', 'model' => 'puter-test']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.puter.com/puterai/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer puter-token')
                && $request['model'] === 'puter-test'
                && count($request['messages']) === 1
                && str_contains($request['messages'][0]['content'], 'Olet JalaOps-huoltopyyntöjärjestelmän avustaja')
                && str_contains($request['messages'][0]['content'], 'Huolla trukki');
        });
    }

    public function test_puter_needs_its_own_token(): void
    {
        // The Gemini key is set in setUp, but it doesn't turn on the Puter provider.
        config(['ai_overview.provider' => 'puter']);
        Http::fake();

        $this->getJson('/api/dashboard/ai-overview')
            ->assertOk()
            ->assertExactJson(['enabled' => false]);
    }

    public function test_switching_the_provider_generates_a_new_overview(): void
    {
        config(['services.puter.auth_token' => 'puter-token']);
        $this->fakeGemini();
        Http::fake(['api.puter.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"summary": "Puterin katsaus.", "actions": [], "risks": []}']]],
        ])]);

        $this->getJson('/api/dashboard/ai-overview')->assertJsonPath('provider', 'Gemini');

        config(['ai_overview.provider' => 'puter']);

        $this->getJson('/api/dashboard/ai-overview')
            ->assertJsonPath('provider', 'Puter')
            ->assertJsonPath('summary', 'Puterin katsaus.');
    }
}
