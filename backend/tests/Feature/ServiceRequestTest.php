<?php

namespace Tests\Feature;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
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
