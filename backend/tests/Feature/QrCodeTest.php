<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RequestItem;
use App\Models\ServiceRequest;
use App\Support\QrImage;
use App\Support\ReferenceNumber;
use chillerlan\QRCode\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    // The total of one row of 1.5 h × 65 € + 25.5 % VAT, as in InvoiceTest.
    private const PAYMENT_QR = "BCD\n002\n1\nSCT\nNDEAFIHH\nJalaOps Demo\nFI2112345600000785\nEUR122.36\n\nRF0810016";

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
        config([
            'billing.seller.name' => 'JalaOps Demo',
            'billing.seller.iban' => 'FI21 1234 5600 0007 85',
            'billing.seller.bic' => 'NDEAFIHH',
            'app.frontend_url' => 'https://jalaops.example/',
        ]);
    }

    private function createInvoice(): int
    {
        $customer = Customer::factory()->create(['payment_terms_days' => 14]);
        $request = ServiceRequest::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);
        RequestItem::factory()->for($request)->create(['quantity' => 1.5, 'unit_price_cents' => 6500]);

        return $this->postJson('/api/invoices', ['customer_id' => $customer->id, 'request_ids' => [$request->id]])
            ->assertCreated()
            ->json('data.id');
    }

    /** The text a phone's camera reads from a QR code image in a document. */
    private function scan(string $html, string $alt): string
    {
        $found = preg_match('/<img src="data:image\/png;base64,([^"]+)" alt="'.preg_quote($alt, '/').'">/', $html, $match);
        $this->assertSame(1, $found, "No QR code \"{$alt}\" in the document.");

        return (string) (new QRCode)->readFromBlob(base64_decode($match[1]));
    }

    public function test_finnish_references_become_rf_creditor_references(): void
    {
        // The example in ISO 11649.
        $this->assertSame('RF18539007547034', ReferenceNumber::toRf('539007547034'));
        $this->assertSame('RF0810016', ReferenceNumber::toRf('1001 6'));

        $this->expectException(\InvalidArgumentException::class);
        ReferenceNumber::toRf('RF12');
    }

    public function test_the_invoice_has_the_payment_details_and_the_payment_qr_code(): void
    {
        $id = $this->createInvoice();

        $this->getJson("/api/invoices/{$id}")
            ->assertOk()
            ->assertJsonPath('data.payment.recipient', 'JalaOps Demo')
            ->assertJsonPath('data.payment.iban', 'FI21 1234 5600 0007 85')
            ->assertJsonPath('data.payment.bic', 'NDEAFIHH')
            ->assertJsonPath('data.payment.qr', self::PAYMENT_QR);
    }

    public function test_the_payment_qr_code_is_left_out_without_an_iban_or_once_paid(): void
    {
        $id = $this->createInvoice();

        config(['billing.seller.iban' => 'FI21 1234']);
        $this->getJson("/api/invoices/{$id}")->assertJsonPath('data.payment.qr', null);

        config(['billing.seller.iban' => null]);
        $this->getJson("/api/invoices/{$id}")
            ->assertJsonPath('data.payment.iban', null)
            ->assertJsonPath('data.payment.qr', null);
        $this->assertStringNotContainsString('Maksun QR-koodi', $this->get("/api/invoices/{$id}/export/html")->getContent());

        config(['billing.seller.iban' => 'FI2112345600000785']);
        $this->patchJson("/api/invoices/{$id}/status", ['status' => 'sent'])->assertOk()->assertJsonPath('data.payment.qr', self::PAYMENT_QR);
        $this->patchJson("/api/invoices/{$id}/status", ['status' => 'paid'])->assertOk()->assertJsonPath('data.payment.qr', null);
    }

    public function test_a_bic_in_the_wrong_format_is_left_out(): void
    {
        config(['billing.seller.bic' => 'NDEA']);

        $this->getJson("/api/invoices/{$this->createInvoice()}")
            ->assertJsonPath('data.payment.qr', str_replace('NDEAFIHH', '', self::PAYMENT_QR));
    }

    public function test_the_invoice_documents_have_a_scannable_payment_qr_code(): void
    {
        $id = $this->createInvoice();

        $html = $this->get("/api/invoices/{$id}/export/html")->assertOk()->getContent();
        $this->assertSame(self::PAYMENT_QR, $this->scan($html, 'Maksun QR-koodi'));
        $this->assertStringContainsString('Maksa pankkisovelluksella', $html);

        $this->assertStringStartsWith('%PDF-', $this->get("/api/invoices/{$id}/export/pdf")->assertOk()->getContent());
    }

    public function test_the_request_documents_have_a_qr_code_that_opens_the_request_in_the_app(): void
    {
        $request = ServiceRequest::factory()->create();

        $html = $this->get("/api/requests/{$request->id}/export/html")->assertOk()->getContent();
        $this->assertSame("https://jalaops.example/requests/{$request->id}", $this->scan($html, 'QR-koodi: avaa pyyntö JalaOpsissa'));
        $this->assertStringContainsString("<a href=\"https://jalaops.example/requests/{$request->id}\">", $html);
        $this->assertStringContainsString('Avaa puhelimella', $html);

        $this->assertStringStartsWith('%PDF-', $this->get("/api/requests/{$request->id}/export/pdf")->assertOk()->getContent());

        config(['app.frontend_url' => null]);
        $this->assertStringNotContainsString('Avaa puhelimella', $this->get("/api/requests/{$request->id}/export/html")->getContent());
    }

    public function test_qr_images_are_png_data_uris(): void
    {
        $this->assertStringStartsWith('data:image/png;base64,', QrImage::dataUri('https://jalaops.example/requests/1'));
    }
}
