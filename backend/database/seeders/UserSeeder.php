<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * The demo accounts behind the login page's demo buttons, when the
     * buttons are on (DEMO_LOGIN). The demo login also creates a missing one.
     */
    public function run(): void
    {
        if (! config('auth.demo_login')) {
            return;
        }

        foreach (UserRole::cases() as $role) {
            User::demoAccount($role);
        }
    }
}
