<?php

namespace Tests\Feature;

use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleApiTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://jalaops.example';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => [self::SITE]]);
    }

    public function test_it_lists_the_modules_and_whether_they_are_on(): void
    {
        $this->getJson('/api/modules')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'id' => 'hello',
                'name' => 'Hei maailma',
                'description' => 'Esimerkkimoduuli: tervehtii ja laskee lukujen summan, keskiarvon ja mediaanin.',
                'version' => '1.0.0',
                'enabled' => true,
            ])
            ->assertJsonPath('data.1.id', 'vat-calculator');

        app(ModuleRegistry::class)->setEnabled('hello', false);

        $this->getJson('/api/modules')->assertJsonPath('data.0.enabled', false);
    }

    public function test_it_shows_a_modules_form_and_results(): void
    {
        $this->getJson('/api/modules/hello')
            ->assertOk()
            ->assertJsonPath('data.id', 'hello')
            ->assertJsonPath('data.form.0', ['name' => 'name', 'label' => 'Nimi', 'type' => 'text', 'default' => 'Maailma'])
            ->assertJsonPath('data.form.1.type', 'numbers')
            ->assertJsonPath('data.results.0', ['name' => 'message', 'label' => 'Tervehdys'])
            ->assertJsonMissingPath('data.directory')
            ->assertJsonMissingPath('data.entry');
    }

    public function test_it_sends_the_modules_own_view_inside_json(): void
    {
        $response = $this->getJson('/api/modules/hello')->assertOk();

        $this->assertStringContainsString('jalaops.run(', $response->json('data.view'));
        $this->assertSame(['style', 'script'], array_column($response->json('data.assets'), 'type'));
        $this->assertStringContainsString('window.barChart', $response->json('data.assets.1.content'));
        $this->assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
    }

    public function test_a_module_that_is_off_sends_no_view(): void
    {
        app(ModuleRegistry::class)->setEnabled('hello', false);

        $this->getJson('/api/modules/hello')->assertOk()->assertJsonPath('data.view', null)->assertJsonPath('data.assets', []);
    }

    public function test_results_can_have_a_fixed_number_of_decimals(): void
    {
        $this->getJson('/api/modules/vat-calculator')
            ->assertOk()
            ->assertJsonPath('data.results.2', ['name' => 'total', 'label' => 'Verollinen hinta (€)', 'decimals' => 2])
            ->assertJsonPath('data.form.1.options', ['25.5', '14', '10', '0'])
            ->assertJsonPath('data.view', null);
    }

    public function test_an_unknown_module_is_not_found(): void
    {
        $this->getJson('/api/modules/nope')
            ->assertNotFound()
            ->assertJsonPath('message', 'Moduulia ei löytynyt.');

        $this->withHeader('Origin', self::SITE)
            ->postJson('/api/modules/nope/run')
            ->assertNotFound();
    }

    public function test_the_app_can_run_a_module(): void
    {
        $this->withHeader('Origin', self::SITE)
            ->postJson('/api/modules/hello/run', ['name' => 'Jussi', 'numbers' => [3, 5, 10]])
            ->assertOk()
            ->assertJsonPath('output.message', 'Hei, Jussi!')
            ->assertJsonPath('output.sum', 18);
    }

    public function test_other_websites_cannot_run_modules(): void
    {
        $this->withHeader('Origin', 'https://evil.example')
            ->postJson('/api/modules/hello/run')
            ->assertForbidden();

        $this->postJson('/api/modules/hello/run')->assertForbidden();
    }

    public function test_a_module_that_is_off_cannot_be_run(): void
    {
        app(ModuleRegistry::class)->setEnabled('hello', false);

        $this->withHeader('Origin', self::SITE)
            ->postJson('/api/modules/hello/run')
            ->assertForbidden()
            ->assertJsonPath('message', 'Moduuli ei ole käytössä.');
    }

    public function test_a_failing_module_answers_with_its_reason(): void
    {
        $this->withHeader('Origin', self::SITE)
            ->postJson('/api/modules/hello/run', ['numbers' => ['kolme']])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Moduulin suoritus epäonnistui.',
                'reason' => 'Lukujen pitää olla lista numeroita.',
            ]);
    }

    public function test_the_input_must_be_a_small_json_object(): void
    {
        $this->withHeader('Origin', self::SITE)
            ->postJson('/api/modules/hello/run', [1, 2, 3])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Syötteen pitää olla JSON-objekti, enintään 10 kt.');

        $this->withHeader('Origin', self::SITE)
            ->postJson('/api/modules/hello/run', ['name' => str_repeat('a', 11_000)])
            ->assertUnprocessable();
    }

    public function test_running_is_limited_per_visitor(): void
    {
        $this->withHeader('Origin', self::SITE);

        foreach (range(1, 30) as $attempt) {
            $this->postJson('/api/modules/hello/run')->assertOk();
        }

        $this->postJson('/api/modules/hello/run')->assertTooManyRequests();
    }
}
