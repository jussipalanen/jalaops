<?php

namespace App\Enums;

enum RequestPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    /**
     * Finnish label, as shown in the app.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Matala',
            self::Normal => 'Normaali',
            self::High => 'Korkea',
        };
    }
}
