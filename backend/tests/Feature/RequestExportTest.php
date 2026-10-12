<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestExportTest extends TestCase
{
    use RefreshDatabase;

    // A real 1×1 PNG.
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function signedRequest(array $overrides = []): ServiceRequest
    {
        return ServiceRequest::factory()->create(array_merge([
            'title' => 'Vaihda ilmansuodatin',
            'description' => 'Varaston ilmanvaihtokone.',
            'priority' => 'high',
            'status' => 'completed',
            'due_date' => '2026-10-15',
            'street_address' => 'Tikkurilantie 44',
            'postal_code' => '01300',
            'city' => 'Vantaa',
            'latitude' => 60.2891,
            'longitude' => 25.0371,
            'completion_signature' => self::PNG,
            'signed_by' => 'Maija Huoltaja',
        ]));
    }

    public function test_downloads_a_pdf(): void
    {
        $request = $this->signedRequest();

        $response = $this->get("/api/requests/{$request->id}/export/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', "attachment; filename=pyynto-{$request->id}-vaihda-ilmansuodatin.pdf");

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_downloads_a_self_contained_html_file(): void
    {
        $this->travelTo('2026-10-12 12:00:00');
        $request = $this->signedRequest();
        $request->forceFill(['signed_at' => now()])->save();

        $response = $this->get("/api/requests/{$request->id}/export/html")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Content-Disposition', "attachment; filename=pyynto-{$request->id}-vaihda-ilmansuodatin.html");

        $html = $response->getContent();
        $this->assertStringContainsString('<h1>Vaihda ilmansuodatin</h1>', $html);
        $this->assertStringContainsString('Prioriteetti: Korkea', $html);
        $this->assertStringContainsString('Tila: Valmis', $html);
        $this->assertStringContainsString('Tikkurilantie 44, 01300 Vantaa', $html);
        $this->assertStringContainsString('15.10.2026', $html);
        // The signature is built into the file, and times are Finnish time (UTC+3 in October).
        $this->assertStringContainsString('<img src="'.self::PNG.'"', $html);
        $this->assertStringContainsString('Maija Huoltaja', $html);
        $this->assertStringContainsString('12.10.2026 klo 15.00', $html);
        $this->assertStringNotContainsString('Nimenselvennys', $html);
        // No external stylesheets, scripts or images.
        $this->assertDoesNotMatchRegularExpression('/<(link|script)\b|src="http/i', $html);
    }

    public function test_unsigned_requests_get_lines_for_signing_by_hand(): void
    {
        $request = ServiceRequest::factory()->create(['status' => 'open']);

        $html = $this->get("/api/requests/{$request->id}/export/html")->assertOk()->getContent();

        $this->assertStringContainsString('Allekirjoitus', $html);
        $this->assertStringContainsString('Nimenselvennys', $html);
        $this->assertStringContainsString('Päiväys', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_user_text_is_escaped(): void
    {
        $request = ServiceRequest::factory()->create(['title' => '<script>alert(1)</script>', 'description' => '<b>lihavoitu</b>']);

        $html = $this->get("/api/requests/{$request->id}/export/html")->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<b>lihavoitu</b>', $html);
    }

    public function test_unknown_format_or_request_returns_404(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->getJson("/api/requests/{$request->id}/export/docx")->assertNotFound();
        $this->getJson('/api/requests/999/export/pdf')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Pyyntöä ei löytynyt.']);
    }
}
