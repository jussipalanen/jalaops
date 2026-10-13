<?php

namespace Tests\Feature;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_example_requests(): void
    {
        $this->seed();

        $this->assertDatabaseCount('service_requests', 15);

        $request = ServiceRequest::where('title', 'Vaihda ilmansuodatin')->firstOrFail();
        $this->assertSame(RequestPriority::High, $request->priority);
        $this->assertSame(RequestStatus::Open, $request->status);
    }

    public function test_seeding_twice_does_not_duplicate_requests(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('service_requests', 15);
    }

    public function test_seeded_requests_have_finnish_locations(): void
    {
        $this->seed();

        // Two demo requests are left without a location on purpose.
        $this->assertSame(13, ServiceRequest::whereNotNull('latitude')->count());

        $request = ServiceRequest::where('title', 'Vaihda ilmansuodatin')->firstOrFail();
        $this->assertSame('Tikkurilantie 44', $request->street_address);
        $this->assertSame('01300', $request->postal_code);
        $this->assertSame('Vantaa', $request->city);

        // Every location is inside Finland.
        foreach (ServiceRequest::whereNotNull('latitude')->get() as $located) {
            $this->assertTrue($located->latitude > 59.5 && $located->latitude < 70.1, $located->title);
            $this->assertTrue($located->longitude > 19.0 && $located->longitude < 31.6, $located->title);
        }
    }

    public function test_seeding_adds_locations_to_existing_demo_requests(): void
    {
        $existing = ServiceRequest::factory()->create(['title' => 'Huolla trukki']);
        $own = ServiceRequest::factory()->create(['title' => 'Oma pyyntö']);

        $this->seed();

        $this->assertDatabaseCount('service_requests', 2);
        $this->assertSame('Oulu', $existing->fresh()->city);
        $this->assertNull($own->fresh()->city);
    }

    public function test_only_some_seeded_requests_are_new(): void
    {
        $this->seed();

        $new = ServiceRequest::where('created_at', '>=', now()->subDays(7))
            ->where('status', '!=', 'completed')
            ->pluck('title')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'Huolla trukki',
            'Säädä toimiston lämmitys',
            'Tarkasta varaston nosto-ovi',
            'Vaihda WC:n poistoilmapuhallin',
            'Vaihda ilmansuodatin',
        ], $new);
    }

    public function test_seeded_requests_belong_to_demo_customers(): void
    {
        $this->seed();

        $this->assertSame(4, Customer::count());
        $this->assertSame(0, ServiceRequest::whereNull('customer_id')->count());
        $this->assertSame('Esimerkki Kiinteistöt Oy', ServiceRequest::where('title', 'Vaihda ilmansuodatin')->first()->customer->name);
        // Made-up contact details only.
        $this->assertSame(0, Customer::where('email', 'not like', '%.example.com')->count());
    }

    public function test_seeding_adds_a_sent_and_a_paid_demo_invoice(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(['paid', 'sent'], Invoice::query()->orderBy('status')->pluck('status')->map->value->all());
        $this->assertTrue(Invoice::where('status', 'sent')->first()->isOverdue());
        // Some completed requests are left to invoice in the demo.
        $this->assertTrue(ServiceRequest::where('title', 'Vaihda toimiston loisteputket')->whereNull('invoice_id')->exists());
    }

    public function test_seeded_requests_cover_every_status_and_priority(): void
    {
        $this->seed();

        foreach (RequestStatus::cases() as $status) {
            $this->assertTrue(ServiceRequest::where('status', $status)->exists(), "Missing status {$status->value}");
        }

        foreach (RequestPriority::cases() as $priority) {
            $this->assertTrue(ServiceRequest::where('priority', $priority)->exists(), "Missing priority {$priority->value}");
        }
    }

    public function test_new_request_defaults_to_normal_priority_and_open_status(): void
    {
        $request = ServiceRequest::create(['title' => 'Testipyyntö']);

        $this->assertSame(RequestPriority::Normal, $request->priority);
        $this->assertSame(RequestStatus::Open, $request->status);
        $this->assertNull($request->due_date);
    }

    public function test_due_date_is_serialized_as_a_plain_date(): void
    {
        $request = ServiceRequest::factory()->create(['due_date' => '2026-10-15']);

        $this->assertSame('2026-10-15', $request->toArray()['due_date']);
    }
}
