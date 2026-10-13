<?php

namespace App\Enums;

enum RequestItemType: string
{
    case Work = 'work';
    case Material = 'material';

    /**
     * Finnish label, as shown in the app.
     */
    public function label(): string
    {
        return match ($this) {
            self::Work => 'Työ',
            self::Material => 'Tarvike',
        };
    }
}
