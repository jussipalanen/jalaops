<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_requests_by_status_and_overdue(): void
    {
        $this->travelTo('2026-10-12 12:00');

        ServiceRequest::factory()->create(['status' => 'open', 'due_date' => '2026-10-01']);
        ServiceRequest::factory()->create(['status' => 'open', 'due_date' => '2026-10-20']);
        ServiceRequest::factory()->create(['status' => 'open', 'due_date' => null]);
        ServiceRequest::factory()->create(['status' => 'in_progress', 'due_date' => '2026-10-11']);
        // Completed requests are never overdue.
        ServiceRequest::factory()->create(['status' => 'completed', 'due_date' => '2026-09-01']);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertExactJson([
                'open' => 3,
                'in_progress' => 1,
                'completed' => 1,
                'overdue' => 2,
                'total' => 5,
            ]);
    }

    public function test_counts_are_zero_without_requests(): void
    {
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertExactJson(['open' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0, 'total' => 0]);
    }
}
