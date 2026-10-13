<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Esimerkki Kiinteistöt Oy',
            // 1234567 weighted 7,9,10,5,8,4,2 sums to 153; 153 % 11 = 10; check digit 11 - 10 = 1.
            'business_id' => '1234567-1',
            'contact_person' => 'Maija Mallikas',
            'email' => 'laskutus@esimerkki.example.com',
            'phone' => '09 123 4567',
            'billing_street_address' => 'Mannerheimintie 12',
            'billing_postal_code' => '00100',
            'billing_city' => 'Helsinki',
            'payment_terms_days' => 30,
            'notes' => 'Laskut sähköpostiin.',
        ], $overrides);
    }

    public function test_lists_customers_alphabetically_with_request_counts(): void
    {
        $busy = Customer::factory()->create(['name' => 'Pirkan Demohalli Oy']);
        Customer::factory()->create(['name' => 'Esimerkki Kiinteistöt Oy']);
        ServiceRequest::factory()->count(2)->create(['customer_id' => $busy->id]);

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Esimerkki Kiinteistöt Oy')
            ->assertJsonPath('data.0.requests_count', 0)
            ->assertJsonPath('data.1.name', 'Pirkan Demohalli Oy')
            ->assertJsonPath('data.1.requests_count', 2);
    }

    public function test_searches_customers(): void
    {
        Customer::factory()->create(['name' => 'Pirkan Demohalli Oy', 'billing_city' => 'Tampere', 'contact_person' => 'Matti Meikäläinen']);
        Customer::factory()->create(['name' => 'Pohjoisen Testivarasto Oy', 'billing_city' => 'Oulu', 'contact_person' => 'Liisa Esimerkki']);

        $names = fn (string $search) => collect($this->getJson('/api/customers?search='.urlencode($search))->json('data'))->pluck('name')->all();

        $this->assertSame(['Pirkan Demohalli Oy'], $names('tampere'));
        $this->assertSame(['Pohjoisen Testivarasto Oy'], $names('Liisa'));
        $this->assertSame(['Pohjoisen Testivarasto Oy'], $names('testi'));
    }

    public function test_creates_shows_and_updates_a_customer(): void
    {
        $id = $this->postJson('/api/customers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.business_id', '1234567-1')
            ->assertJsonPath('data.payment_terms_days', 30)
            ->assertJsonPath('data.requests_count', 0)
            ->json('data.id');

        $this->getJson("/api/customers/{$id}")->assertOk()->assertJsonPath('data.name', 'Esimerkki Kiinteistöt Oy');

        $this->putJson("/api/customers/{$id}", $this->payload(['name' => 'Uusi nimi Oy', 'business_id' => null]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Uusi nimi Oy')
            ->assertJsonPath('data.business_id', null);
    }

    public function test_checks_the_business_id(): void
    {
        $this->postJson('/api/customers', $this->payload(['business_id' => '1234567-4']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.business_id.0', 'Y-tunnuksen tarkistusmerkki ei täsmää.');

        $this->postJson('/api/customers', $this->payload(['business_id' => '12345673']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.business_id.0', 'Y-tunnuksen muoto on 1234567-8.');

        // 1000001 sums to 7 + 2 = 9; 11 - 9 = 2.
        $this->postJson('/api/customers', $this->payload(['business_id' => '1000001-2']))->assertCreated();
    }

    public function test_validates_customers_in_finnish(): void
    {
        $this->postJson('/api/customers', ['email' => 'ei-osoite', 'payment_terms_days' => 400])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Kenttä nimi on pakollinen.')
            ->assertJsonPath('errors.email.0', 'Kentän sähköposti on oltava sähköpostiosoite.')
            ->assertJsonPath('errors.payment_terms_days.0', 'Kentän maksuehto on oltava välillä 0–365.');
    }

    public function test_a_customer_with_requests_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create();
        $request = ServiceRequest::factory()->create(['customer_id' => $customer->id]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Asiakasta ei voi poistaa, koska sillä on pyyntöjä. Vaihda pyyntöjen asiakas tai poista pyynnöt ensin.');

        $request->delete();
        $this->deleteJson("/api/customers/{$customer->id}")->assertNoContent();
        $this->getJson("/api/customers/{$customer->id}")->assertNotFound()->assertExactJson(['message' => 'Asiakasta ei löytynyt.']);
    }

    public function test_requests_have_a_customer_and_can_be_filtered_by_it(): void
    {
        $customer = Customer::factory()->create(['name' => 'Pirkan Demohalli Oy']);
        ServiceRequest::factory()->create(['title' => 'Muu']);

        $id = $this->postJson('/api/requests', ['title' => 'Huolla trukki', 'priority' => 'high', 'status' => 'open', 'customer_id' => $customer->id])
            ->assertCreated()
            ->assertJsonPath('data.customer', ['id' => $customer->id, 'name' => 'Pirkan Demohalli Oy'])
            ->json('data.id');

        $this->getJson("/api/requests/{$id}")->assertJsonPath('data.customer.name', 'Pirkan Demohalli Oy');
        $this->getJson("/api/requests?customer={$customer->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Huolla trukki');

        $this->postJson('/api/requests', ['title' => 'X', 'priority' => 'low', 'status' => 'open', 'customer_id' => 999])
            ->assertUnprocessable()
            ->assertJsonPath('errors.customer_id.0', 'Valittua kohdetta (asiakas) ei löytynyt.');
    }
}
