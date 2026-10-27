<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'demo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The shared demo accounts, by role: the login page's demo buttons sign
     * in as these.
     */
    public const DEMO_ACCOUNTS = [
        'user' => ['name' => 'Demokäyttäjä', 'email' => 'demo.kayttaja@example.com'],
        'admin' => ['name' => 'Demoylläpitäjä', 'email' => 'demo.yllapitaja@example.com'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'demo' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * The demo account of the role, created when it's missing. It has a
     * random password: only the demo buttons sign in to it.
     */
    public static function demoAccount(UserRole $role): self
    {
        $account = self::DEMO_ACCOUNTS[$role->value];

        return self::firstOrCreate(['email' => $account['email']], [
            'name' => $account['name'],
            'password' => Str::random(40),
            'role' => $role,
            'demo' => true,
        ]);
    }
}
