<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionSignatureTest extends TestCase
{
    use RefreshDatabase;

    // A real 1×1 PNG.
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Vaihda ilmansuodatin',
            'priority' => 'high',
            'status' => 'completed',
        ], $overrides);
    }

    public function test_completing_with_a_signature_stores_the_sign_off(): void
    {
        $this->travelTo('2026-10-12 15:30');

        $id = $this->postJson('/api/requests', $this->payload(['signed_by' => 'Maija Huoltaja', 'completion_signature' => self::PNG]))
            ->assertCreated()
            ->assertJsonPath('data.has_signature', true)
            ->assertJsonPath('data.signed_by', 'Maija Huoltaja')
            ->assertJsonPath('data.signed_at', '2026-10-12T15:30:00.000000Z')
            ->assertJsonMissingPath('data.completion_signature')
            ->json('data.id');

        $this->get("/api/requests/{$id}/signature")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent(base64_decode(substr(self::PNG, 22)));
    }

    public function test_a_request_without_a_signature_has_no_image(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->getJson("/api/requests/{$request->id}/signature")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Pyynnöllä ei ole allekirjoitusta.']);
    }

    public function test_saving_without_the_signature_keeps_it_and_null_removes_it(): void
    {
        $id = $this->postJson('/api/requests', $this->payload(['signed_by' => 'Maija Huoltaja', 'completion_signature' => self::PNG]))->json('data.id');
        $signedAt = ServiceRequest::find($id)->signed_at;

        $this->travel(1)->hours();

        // The edit form leaves the signature out when it isn't redrawn.
        $this->putJson("/api/requests/{$id}", $this->payload(['title' => 'Uusi otsikko', 'signed_by' => 'Maija Huoltaja']))
            ->assertOk()
            ->assertJsonPath('data.has_signature', true);
        $this->assertEquals($signedAt, ServiceRequest::find($id)->signed_at);

        $this->putJson("/api/requests/{$id}", $this->payload(['signed_by' => null, 'completion_signature' => null]))
            ->assertOk()
            ->assertJsonPath('data.has_signature', false)
            ->assertJsonPath('data.signed_at', null);
    }

    public function test_reopening_a_request_removes_the_sign_off(): void
    {
        $id = $this->postJson('/api/requests', $this->payload(['signed_by' => 'Maija Huoltaja', 'completion_signature' => self::PNG]))->json('data.id');

        $this->putJson("/api/requests/{$id}", $this->payload(['status' => 'in_progress']))
            ->assertOk()
            ->assertJsonPath('data.has_signature', false)
            ->assertJsonPath('data.signed_by', null)
            ->assertJsonPath('data.signed_at', null);
    }

    public function test_rejects_invalid_sign_offs(): void
    {
        $this->postJson('/api/requests', $this->payload(['completion_signature' => 'data:image/jpeg;base64,/9j/4AAQ']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.completion_signature.0', 'Allekirjoituksen on oltava PNG-kuva.')
            ->assertJsonPath('errors.signed_by.0', 'Kenttä kuittaajan nimi on pakollinen, kun allekirjoitus on annettu.');

        // Base64 that isn't a PNG.
        $this->postJson('/api/requests', $this->payload(['signed_by' => 'Maija', 'completion_signature' => 'data:image/png;base64,'.base64_encode('<svg/>')]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.completion_signature.0', 'Allekirjoituksen on oltava PNG-kuva.');

        $this->postJson('/api/requests', $this->payload(['status' => 'open', 'signed_by' => 'Maija', 'completion_signature' => self::PNG]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.completion_signature.0', 'Allekirjoituksen voi lisätä vain valmiille pyynnölle.')
            ->assertJsonPath('errors.signed_by.0', 'Kuittaajan voi antaa vain valmiille pyynnölle.');
    }
}
