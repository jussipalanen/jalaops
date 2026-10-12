<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RequestTransferTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function csvFile(string $content, string $name = 'pyynnot.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_exports_the_filtered_requests_as_csv_for_excel(): void
    {
        ServiceRequest::factory()->create(['title' => 'Avoin', 'status' => 'open', 'city' => 'Tampere', 'latitude' => 61.4980, 'longitude' => 23.7660]);
        ServiceRequest::factory()->create(['title' => 'Valmis', 'status' => 'completed']);

        $response = $this->get('/api/requests/export?format=csv&status=open')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $this->assertMatchesRegularExpression('/attachment; filename=jalaops-pyynnot-\d{4}-\d{2}-\d{2}\.csv/', $response->headers->get('Content-Disposition'));

        $csv = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFid;title;description;priority;status;due_date;", $csv);
        $this->assertStringContainsString(';Avoin;', $csv);
        $this->assertStringContainsString(';Tampere;61.498;23.766;', $csv);
        $this->assertStringNotContainsString('Valmis', $csv);
    }

    public function test_exports_the_chosen_requests_as_json_with_signatures(): void
    {
        $chosen = ServiceRequest::factory()->create(['title' => 'Valittu', 'status' => 'completed', 'completion_signature' => self::PNG, 'signed_by' => 'Maija']);
        ServiceRequest::factory()->create(['title' => 'Muu']);

        $json = $this->get("/api/requests/export?format=json&ids={$chosen->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->json();

        $this->assertSame('JalaOps', $json['app']);
        $this->assertCount(1, $json['requests']);
        $this->assertSame('Valittu', $json['requests'][0]['title']);
        $this->assertSame(self::PNG, $json['requests'][0]['completion_signature']);
    }

    public function test_csv_cells_that_excel_would_run_as_formulas_are_kept_as_text(): void
    {
        ServiceRequest::factory()->create(['title' => '=HYPERLINK("http://example.com")']);

        $csv = $this->get('/api/requests/export?format=csv')->getContent();

        $this->assertStringContainsString('"\'=HYPERLINK(""http://example.com"")"', $csv);
    }

    public function test_an_export_imports_back_as_new_requests(): void
    {
        ServiceRequest::factory()->create(['title' => 'Vaihda ilmansuodatin', 'priority' => 'high', 'status' => 'open', 'due_date' => '2026-10-15', 'city' => 'Vantaa']);
        ServiceRequest::factory()->create(['title' => '=Kaava', 'status' => 'completed', 'completion_signature' => self::PNG, 'signed_by' => 'Maija']);

        // Both exports first, so the second doesn't include the first import.
        $exports = collect(['csv', 'json'])->mapWithKeys(fn ($format) => [$format => $this->get("/api/requests/export?format={$format}")->getContent()]);

        foreach ($exports as $format => $content) {
            $file = $this->csvFile($content, "vienti.{$format}");

            $this->post('/api/requests/import', ['file' => $file], ['Accept' => 'application/json'])
                ->assertCreated()
                ->assertJsonPath('total', 2)
                ->assertJsonPath('imported', 2);
        }

        $this->assertSame(3, ServiceRequest::where('title', 'Vaihda ilmansuodatin')->count());
        // The formula guard is removed again, and the JSON copy brings the signature.
        $this->assertSame(3, ServiceRequest::where('title', '=Kaava')->count());
        $this->assertSame(2, ServiceRequest::where('title', '=Kaava')->whereNotNull('completion_signature')->count());
    }

    public function test_dry_run_reports_without_importing(): void
    {
        $csv = "title;priority;status;due_date;city;latitude;longitude\n"
            ."Huolla trukki;Korkea;Avoin;15.10.2026;Oulu;65,0103;25,4701\n"
            .";normal;open;;;;\n"
            ."Väärä tila;normal;kesken;;;;\n";

        $this->post('/api/requests/import', ['file' => $this->csvFile($csv), 'dry_run' => '1'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('dry_run', true)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('valid', 1)
            ->assertJsonPath('invalid', 2)
            ->assertJsonPath('imported', 0)
            // Finnish labels, dates and decimal commas are understood.
            ->assertJsonPath('preview.0', ['title' => 'Huolla trukki', 'priority' => 'high', 'status' => 'open', 'due_date' => '2026-10-15', 'city' => 'Oulu'])
            ->assertJsonPath('errors.0.row', 3)
            ->assertJsonPath('errors.0.messages.0', 'Kenttä otsikko on pakollinen.')
            ->assertJsonPath('errors.1.row', 4)
            ->assertJsonPath('errors.1.title', 'Väärä tila')
            ->assertJsonPath('errors.1.messages.0', 'Kentän tila arvo on virheellinen.');

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_imports_nothing_when_any_row_is_invalid(): void
    {
        $csv = "title,priority,status\nHyvä,normal,open\n,normal,open\n";

        $this->post('/api/requests/import', ['file' => $this->csvFile($csv)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tiedostossa on virheellisiä rivejä, joten mitään ei tuotu. Korjaa rivit ja yritä uudelleen.')
            ->assertJsonPath('imported', 0);

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_rejects_unreadable_files(): void
    {
        $this->post('/api/requests/import', ['file' => $this->csvFile("nimi;tila\nA;B\n")], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'CSV-tiedoston ensimmäisellä rivillä pitää olla sarakkeiden nimet, vähintään title, priority ja status.');

        $this->post('/api/requests/import', ['file' => $this->csvFile('{"requests": [', 'rikki.json')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'JSON-tiedostoa ei voitu lukea. Tarkista, että se on JalaOpsin vientitiedosto tai muuten kelvollista JSONia.');

        $this->post('/api/requests/import', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Kenttä tiedosto on pakollinen.');
    }

    public function test_rejects_invalid_export_parameters(): void
    {
        $this->getJson('/api/requests/export?format=xlsx&ids=1;2')
            ->assertUnprocessable()
            ->assertJsonPath('errors.format.0', 'Kentän muoto arvo on virheellinen.')
            ->assertJsonPath('errors.ids.0', 'Kentän valitut pyynnöt muoto on virheellinen.');
    }
}
