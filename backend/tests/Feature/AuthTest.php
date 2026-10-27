<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://jalaops.example';

    protected function setUp(): void
    {
        parent::setUp();

        // These tests start logged out.
        Auth::forgetGuards();
        config(['cors.allowed_origins' => [self::SITE], 'auth.demo_login' => true]);
    }

    public function test_the_api_needs_a_login(): void
    {
        $this->getJson('/api/requests')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Kirjaudu ensin sisään.']);
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();

        // A page opened in a new tab, such as a PDF, gets the same answer.
        $this->get('/api/requests/1/export/pdf')->assertUnauthorized();
    }

    public function test_the_health_check_app_details_and_login_options_are_public(): void
    {
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/system')->assertOk();
        $this->getJson('/api/login')->assertOk()->assertExactJson(['demo_login' => true]);

        config(['auth.demo_login' => false]);

        $this->getJson('/api/login')->assertExactJson(['demo_login' => false]);
    }

    public function test_a_user_logs_in_and_out(): void
    {
        User::factory()->create(['name' => 'Huoltoteknikko', 'email' => 'teknikko@example.com', 'password' => 'oikea-salasana']);

        $this->postJson('/api/login', ['email' => 'teknikko@example.com', 'password' => 'oikea-salasana'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Huoltoteknikko')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.role_label', 'Käyttäjä')
            ->assertJsonPath('data.demo', false)
            ->assertJsonMissingPath('data.password');

        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.email', 'teknikko@example.com');
        $this->getJson('/api/requests')->assertOk();

        $this->postJson('/api/logout')->assertNoContent();

        Auth::forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_wrong_password_is_refused(): void
    {
        User::factory()->create(['email' => 'teknikko@example.com', 'password' => 'oikea-salasana']);

        $this->postJson('/api/login', ['email' => 'teknikko@example.com', 'password' => 'väärä'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Sähköposti tai salasana on väärin.');

        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Kenttä sähköposti on pakollinen.')
            ->assertJsonPath('errors.password.0', 'Kenttä salasana on pakollinen.');

        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => 'teknikko@example.com', 'password' => 'väärä'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'teknikko@example.com', 'password' => 'väärä'])->assertTooManyRequests();

        // The demo buttons have a limit of their own.
        $this->postJson('/api/login/demo', ['role' => 'user'])->assertOk();
    }

    public function test_the_demo_buttons_sign_in_to_the_demo_accounts(): void
    {
        $this->postJson('/api/login/demo', ['role' => 'user'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Demokäyttäjä')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.demo', true);

        $this->postJson('/api/login/demo', ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Demoylläpitäjä')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.role_label', 'Ylläpitäjä');

        // The accounts are created once and then reused.
        $this->postJson('/api/login/demo', ['role' => 'user'])->assertOk();
        $this->assertSame(2, User::where('demo', true)->count());

        $this->postJson('/api/login/demo', ['role' => 'owner'])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_the_demo_login_is_off_outside_demo_mode(): void
    {
        config(['auth.demo_login' => false]);

        $this->postJson('/api/login/demo', ['role' => 'admin'])->assertNotFound();
        $this->assertSame(0, User::count());
    }

    public function test_only_admins_turn_modules_on_and_off(): void
    {
        $this->actingAsRole(UserRole::User)->withHeader('Origin', self::SITE);

        $this->patchJson('/api/modules/hello', ['enabled' => false])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Sinulla ei ole oikeutta tähän toimintoon.']);

        // Users still list and run the modules.
        $this->getJson('/api/modules')->assertOk();
        $this->postJson('/api/modules/hello/run', ['name' => 'Testi'])->assertOk();

        $this->actingAsAdmin()->patchJson('/api/modules/hello', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_the_admin_is_created_from_the_environment(): void
    {
        $this->artisan('users:admin')->expectsOutputToContain('no admin to create')->assertSuccessful();
        $this->assertSame(0, User::count());

        config(['auth.admin' => ['email' => 'admin@example.com', 'password' => 'pitka-salasana']]);

        $this->artisan('users:admin')->expectsOutputToContain('Admin admin@example.com created.')->assertSuccessful();
        $this->artisan('users:admin')->expectsOutputToContain('already exists')->assertSuccessful();

        $admin = User::sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertFalse($admin->demo);

        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'pitka-salasana'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_the_seeder_adds_the_demo_accounts_when_the_demo_buttons_are_on(): void
    {
        config(['auth.demo_login' => false]);
        $this->seed();
        $this->assertSame(0, User::count());

        config(['auth.demo_login' => true]);
        $this->seed();
        $this->assertEqualsCanonicalizing(
            ['demo.kayttaja@example.com', 'demo.yllapitaja@example.com'],
            User::pluck('email')->all(),
        );
    }
}
