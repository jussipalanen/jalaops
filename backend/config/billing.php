<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing defaults
    |--------------------------------------------------------------------------
    |
    | Pre-filled values for new rows of work and materials. Each row keeps its
    | own price and VAT rate, so changing these doesn't change existing rows.
    |
    */

    // Finland's general VAT rate (25.5 % since September 2024).
    'default_vat_rate' => (float) env('DEFAULT_VAT_RATE', 25.5),

    // Hourly rate for work rows, without VAT, in euros.
    'default_hourly_rate' => (float) env('DEFAULT_HOURLY_RATE', 65),

];
