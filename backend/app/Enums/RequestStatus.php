<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * Finnish label, as shown in the app.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Avoin',
            self::InProgress => 'Työn alla',
            self::Completed => 'Valmis',
        };
    }
}
