<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';

    /**
     * Finnish label, as shown in the app.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Luonnos',
            self::Sent => 'Lähetetty',
            self::Paid => 'Maksettu',
        };
    }
}
