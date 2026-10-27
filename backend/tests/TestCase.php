<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test calls the API as a logged-in user, unless it logs out
     * (AuthTest) or acts as an admin.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsRole(UserRole::User);
    }

    protected function actingAsAdmin(): static
    {
        return $this->actingAsRole(UserRole::Admin);
    }

    /**
     * A user that isn't saved, so tests without a database can log in too.
     */
    protected function actingAsRole(UserRole $role): static
    {
        return $this->actingAs(User::factory()->make(['id' => 1, 'role' => $role]));
    }
}
