<?php

namespace Tests\Feature;

use App\Models\RequestItem;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestItemTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $overrides = []): array
    {
        return array_merge([
            'type' => 'work',
            'description' => 'Loisteputkien vaihto',
            'quantity' => 1.5,
            'unit' => 'h',
            'unit_price' => 65,
            'vat_rate' => 25.5,
        ], $overrides);
    }

    public function test_adds_rows_and_computes_totals_with_vat_rounded_per_row(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->postJson("/api/requests/{$request->id}/items", $this->row())
            ->assertCreated()
            // 1.5 × 65.00 = 97.50; VAT 25.5 % = 24.8625 → 24.86.
            ->assertJsonPath('data.0.net', 97.5)
            ->assertJsonPath('data.0.vat', 24.86)
            ->assertJsonPath('data.0.total', 122.36)
            ->assertJsonPath('defaults', ['vat_rate' => 25.5, 'hourly_rate' => 65]);

        $this->postJson("/api/requests/{$request->id}/items", $this->row(['type' => 'material', 'description' => 'Loisteputki', 'quantity' => 2, 'unit' => 'kpl', 'unit_price' => 8.9]))
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            // VAT is the sum of the rounded rows: 24.86 + 4.54 (17.80 × 25.5 % = 4.539).
            ->assertJsonPath('totals', ['net' => 115.3, 'vat' => 29.4, 'total' => 144.7]);

        $this->getJson("/api/requests/{$request->id}")
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.totals.total', 144.7);
    }

    public function test_rounds_awkward_amounts_to_whole_cents(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->postJson("/api/requests/{$request->id}/items", $this->row(['quantity' => 0.33, 'unit_price' => 9.99, 'vat_rate' => 14]))
            // 0.33 × 999 cents = 329.67 → 330; VAT 14 % = 46.2 → 46.
            ->assertJsonPath('data.0.net', 3.3)
            ->assertJsonPath('data.0.vat', 0.46)
            ->assertJsonPath('data.0.total', 3.76);

        $this->assertDatabaseHas('request_items', ['unit_price_cents' => 999, 'vat_rate_basis_points' => 1400]);
    }

    public function test_updates_and_deletes_a_row(): void
    {
        $request = ServiceRequest::factory()->create();
        $item = RequestItem::factory()->for($request)->create();

        $this->putJson("/api/requests/{$request->id}/items/{$item->id}", $this->row(['quantity' => 2, 'unit_price' => 70]))
            ->assertOk()
            ->assertJsonPath('data.0.net', 140);

        $this->deleteJson("/api/requests/{$request->id}/items/{$item->id}")->assertNoContent();
        $this->assertDatabaseCount('request_items', 0);
    }

    public function test_a_row_is_only_found_under_its_own_request(): void
    {
        $item = RequestItem::factory()->for(ServiceRequest::factory())->create();
        $other = ServiceRequest::factory()->create();

        $this->putJson("/api/requests/{$other->id}/items/{$item->id}", $this->row())->assertNotFound();
        $this->deleteJson("/api/requests/{$other->id}/items/{$item->id}")->assertNotFound();
        $this->assertDatabaseCount('request_items', 1);
    }

    public function test_deleting_a_request_deletes_its_rows(): void
    {
        $request = ServiceRequest::factory()->create();
        RequestItem::factory()->for($request)->count(2)->create();

        $this->deleteJson("/api/requests/{$request->id}")->assertNoContent();

        $this->assertDatabaseCount('request_items', 0);
    }

    public function test_validates_rows_in_finnish(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->postJson("/api/requests/{$request->id}/items", ['type' => 'service', 'quantity' => 0, 'unit_price' => 1.234, 'vat_rate' => 101])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Kentän rivin tyyppi arvo on virheellinen.')
            ->assertJsonPath('errors.description.0', 'Kenttä kuvaus on pakollinen.')
            ->assertJsonPath('errors.quantity.0', 'Kentän määrä on oltava suurempi kuin 0.')
            ->assertJsonPath('errors.unit.0', 'Kenttä yksikkö on pakollinen.')
            ->assertJsonPath('errors.unit_price.0', 'Kentässä yksikköhinta saa olla enintään kaksi desimaalia.')
            ->assertJsonPath('errors.vat_rate.0', 'Kenttä ALV-prosentti saa olla enintään 100.');
    }

    public function test_the_download_lists_the_rows_and_totals(): void
    {
        $request = ServiceRequest::factory()->create();
        RequestItem::factory()->for($request)->create(['description' => 'Loisteputkien vaihto', 'quantity' => 1.5, 'unit_price_cents' => 6500]);

        $html = $this->get("/api/requests/{$request->id}/export/html")->assertOk()->getContent();

        $this->assertStringContainsString('<h2>Työt ja tarvikkeet</h2>', $html);
        $this->assertStringContainsString('1,5 h', $html);
        $this->assertStringContainsString("65,00\u{00A0}€", $html);
        $this->assertStringContainsString("122,36\u{00A0}€", $html);
    }
}
