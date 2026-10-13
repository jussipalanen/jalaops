<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RequestItem;
use App\Models\ServiceRequest;
use App\Support\ReferenceNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        // Noon in Finland, so the UTC and Finnish dates are the same.
        $this->travelTo('2026-10-14 09:00:00');
        $this->customer = Customer::factory()->create(['name' => 'Esimerkki Kiinteistöt Oy', 'payment_terms_days' => 14, 'business_id' => '1234567-1']);
    }

    /** A completed request of the customer with one row. */
    private function billable(array $item = [], array $request = []): ServiceRequest
    {
        $serviceRequest = ServiceRequest::factory()->create(['customer_id' => $this->customer->id, 'status' => 'completed', ...$request]);
        RequestItem::factory()->for($serviceRequest)->create($item);

        return $serviceRequest;
    }

    private function createInvoice(array $requestIds): TestResponse
    {
        return $this->postJson('/api/invoices', ['customer_id' => $this->customer->id, 'request_ids' => $requestIds]);
    }

    public function test_reference_numbers_follow_the_finnish_check_digit_rule(): void
    {
        // The usual textbook example: base 123 → 1232.
        $this->assertSame('1232', ReferenceNumber::fromBase(123));
        $this->assertSame('10016', ReferenceNumber::fromBase(1001));
        $this->assertSame('12344', ReferenceNumber::fromBase(1234));
        $this->assertTrue(ReferenceNumber::isValid('1234 4'));
        $this->assertFalse(ReferenceNumber::isValid('12345'));
        $this->assertSame('1234 56789', ReferenceNumber::format('123456789'));
    }

    public function test_creates_a_draft_invoice_from_completed_requests(): void
    {
        $work = $this->billable(['description' => 'Asennustyö', 'quantity' => 1.5, 'unit_price_cents' => 6500, 'vat_rate_basis_points' => 2550], ['title' => 'Vaihda ilmansuodatin']);
        $food = $this->billable(['description' => 'Kahvitarjoilu', 'type' => 'material', 'quantity' => 2, 'unit' => 'kpl', 'unit_price_cents' => 500, 'vat_rate_basis_points' => 1400]);

        $this->createInvoice([$work->id, $food->id])
            ->assertCreated()
            ->assertJsonPath('data.number', 1001)
            ->assertJsonPath('data.reference_number', '10016')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.issue_date', '2026-10-14')
            // 14 days of payment terms.
            ->assertJsonPath('data.due_date', '2026-10-28')
            ->assertJsonPath('data.customer.name', 'Esimerkki Kiinteistöt Oy')
            ->assertJsonPath('data.customer_business_id', '1234567-1')
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.request_title', 'Vaihda ilmansuodatin')
            // VAT by rate: 97.50 × 25.5 % = 24.86 and 10.00 × 14 % = 1.40.
            ->assertJsonPath('data.vat_breakdown', [['rate' => 25.5, 'net' => 97.5, 'vat' => 24.86], ['rate' => 14, 'net' => 10, 'vat' => 1.4]])
            ->assertJsonPath('data.totals', ['net' => 107.5, 'vat' => 26.26, 'total' => 133.76]);

        $this->assertSame(1001, $work->fresh()->invoice->number);
        $this->assertNotNull($food->fresh()->invoice_id);

        // The next invoice gets the next number.
        $this->createInvoice([$this->billable()->id])->assertJsonPath('data.number', 1002)->assertJsonPath('data.reference_number', '10029');
    }

    public function test_an_invoice_keeps_its_copies_when_the_customer_or_rows_change_later(): void
    {
        $request = $this->billable(['description' => 'Asennustyö']);
        $id = $this->createInvoice([$request->id])->json('data.id');

        $this->customer->update(['name' => 'Uusi nimi Oy', 'billing_city' => 'Oulu']);
        RequestItem::query()->update(['description' => 'Muutettu suoraan kantaan']);

        $this->getJson("/api/invoices/{$id}")
            ->assertJsonPath('data.customer.name', 'Esimerkki Kiinteistöt Oy')
            ->assertJsonPath('data.lines.0.description', 'Asennustyö');
    }

    public function test_only_billable_requests_can_be_invoiced(): void
    {
        $open = $this->billable(request: ['status' => 'open']);
        $other = ServiceRequest::factory()->create(['customer_id' => Customer::factory()->create()->id, 'status' => 'completed']);
        RequestItem::factory()->for($other)->create();
        $empty = ServiceRequest::factory()->create(['customer_id' => $this->customer->id, 'status' => 'completed']);
        $invoiced = $this->billable();
        $this->createInvoice([$invoiced->id])->assertCreated();

        $message = 'Laskulle voi valita vain asiakkaan valmiita pyyntöjä, joilla on töitä tai tarvikkeita ja joita ei ole vielä laskutettu.';
        foreach ([$open, $other, $empty, $invoiced] as $request) {
            $this->createInvoice([$request->id])->assertUnprocessable()->assertJsonPath('errors.request_ids.0', $message);
        }

        $this->postJson('/api/invoices', ['customer_id' => $this->customer->id, 'request_ids' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.request_ids.0', 'Kenttä pyynnöt on pakollinen.');
        $this->assertSame(1, Invoice::count());

        $this->getJson("/api/customers/{$this->customer->id}/billable-requests")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_invoiced_requests_rows_are_locked_until_the_draft_is_deleted(): void
    {
        $request = $this->billable();
        $item = $request->items()->first();
        $id = $this->createInvoice([$request->id])->json('data.id');

        $row = ['type' => 'work', 'description' => 'X', 'quantity' => 1, 'unit' => 'h', 'unit_price' => 1, 'vat_rate' => 25.5];
        $this->postJson("/api/requests/{$request->id}/items", $row)->assertStatus(409)->assertJsonPath('message', fn ($message) => str_contains($message, 'laskulla 1001'));
        $this->putJson("/api/requests/{$request->id}/items/{$item->id}", $row)->assertStatus(409);
        $this->deleteJson("/api/requests/{$request->id}/items/{$item->id}")->assertStatus(409);
        $this->getJson("/api/requests/{$request->id}/items")->assertJsonPath('locked', true);
        $this->getJson("/api/requests/{$request->id}")->assertJsonPath('data.invoice.number', 1001);

        $this->deleteJson("/api/invoices/{$id}")->assertNoContent();

        $this->assertNull($request->fresh()->invoice_id);
        $this->postJson("/api/requests/{$request->id}/items", $row)->assertCreated();
    }

    public function test_moves_through_its_statuses(): void
    {
        $id = $this->createInvoice([$this->billable()->id])->json('data.id');

        $this->patchJson("/api/invoices/{$id}/status", ['status' => 'paid'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Laskun tilaa ei voi vaihtaa: Luonnos → Maksettu.');

        $this->patchJson("/api/invoices/{$id}/status", ['status' => 'sent'])->assertOk()->assertJsonPath('data.status', 'sent');
        $this->assertNotNull(Invoice::find($id)->sent_at);

        $this->deleteJson("/api/invoices/{$id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Vain luonnoksen voi poistaa. Lähetetty lasku säilytetään kirjanpitoa varten.');

        $this->patchJson("/api/invoices/{$id}/status", ['status' => 'paid'])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->patchJson("/api/invoices/{$id}/status", ['status' => 'sent'])->assertOk();
        $this->assertNull(Invoice::find($id)->paid_at);
    }

    public function test_overdue_invoices_are_listed_and_counted_on_the_dashboard(): void
    {
        $sent = $this->createInvoice([$this->billable(['quantity' => 1, 'unit_price_cents' => 10000])->id])->json('data.id');
        $this->patchJson("/api/invoices/{$sent}/status", ['status' => 'sent']);
        $this->createInvoice([$this->billable()->id]);

        $this->getJson('/api/dashboard')->assertJsonPath('invoices', ['unpaid' => 1, 'unpaid_total' => 125.5, 'overdue' => 0]);

        // Fifteen days later the 14-day invoice is past due.
        $this->travel(15)->days();

        $this->getJson('/api/invoices?status=overdue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.overdue', true);
        $this->getJson('/api/invoices?status=draft')->assertJsonCount(1, 'data');
        $this->getJson('/api/dashboard')->assertJsonPath('invoices.overdue', 1);
    }

    public function test_downloads_the_invoice_pdf_and_exports_for_accounting(): void
    {
        $id = $this->createInvoice([$this->billable(['quantity' => 1.5, 'unit_price_cents' => 6500])->id])->json('data.id');

        $pdf = $this->get("/api/invoices/{$id}/export/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=lasku-1001.pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get("/api/invoices/{$id}/export/pdf?inline=1")->assertHeader('Content-Disposition', 'inline; filename=lasku-1001.pdf');

        $csv = $this->get('/api/invoices/export?format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8')->getContent();
        $this->assertStringContainsString('number;reference_number;issue_date;due_date;customer;customer_business_id;status;vat_rate;net;vat;total', $csv);
        $this->assertStringContainsString('1001;10016;2026-10-14;2026-10-28;"Esimerkki Kiinteistöt Oy";1234567-1;draft;25,50;97,50;24,86;122,36', $csv);

        $this->get('/api/invoices/export?format=json')->assertOk()->assertJsonPath('invoices.0.total', 122.36);
    }

    public function test_shows_the_invoice_as_a_self_contained_html_page(): void
    {
        $id = $this->createInvoice([$this->billable(['quantity' => 1.5, 'unit_price_cents' => 6500])->id])->json('data.id');

        $html = $this->get("/api/invoices/{$id}/export/html?inline=1")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Content-Disposition', 'inline; filename=lasku-1001.html')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:")
            ->getContent();

        $this->assertStringContainsString('<title>Lasku 1001</title>', $html);
        $this->assertStringContainsString('LUONNOS', $html);
        $this->assertStringContainsString('Maksettava yhteensä', $html);
        $this->assertStringContainsString("122,36\u{00A0}€", $html);
        // The logo is built in, and nothing is loaded from elsewhere.
        $this->assertStringContainsString('<img src="data:image/png;base64,', $html);
        $this->assertDoesNotMatchRegularExpression('/<(link|script)\b|src="http/i', $html);

        $this->get("/api/invoices/{$id}/export/docx")->assertNotFound();
    }

    public function test_a_customer_with_invoices_cannot_be_deleted(): void
    {
        $request = $this->billable();
        $this->createInvoice([$request->id]);
        $request->delete();

        $this->deleteJson("/api/customers/{$this->customer->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Asiakasta ei voi poistaa, koska sillä on laskuja. Laskut säilytetään kirjanpitoa varten.');
    }

    public function test_a_missing_invoice_gives_a_finnish_404(): void
    {
        $this->getJson('/api/invoices/999')->assertNotFound()->assertExactJson(['message' => 'Laskua ei löytynyt.']);
    }
}
