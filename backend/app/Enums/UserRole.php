<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';

    /**
     * Finnish label, as shown in the app.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Ylläpitäjä',
            self::User => 'Käyttäjä',
        };
    }
}
