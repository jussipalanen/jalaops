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

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    |
    | The number of the first invoice, and the seller shown on invoices. The
    | defaults are made up for the demo; set your own before real use.
    |
    */

    'first_invoice_number' => (int) env('FIRST_INVOICE_NUMBER', 1001),

    'seller' => [
        'name' => env('SELLER_NAME', 'JalaOps Demo'),
        'business_id' => env('SELLER_BUSINESS_ID'),
        'street_address' => env('SELLER_STREET_ADDRESS', 'Esimerkkikatu 1'),
        'postal_code' => env('SELLER_POSTAL_CODE', '00100'),
        'city' => env('SELLER_CITY', 'Helsinki'),
        'email' => env('SELLER_EMAIL', 'laskutus@jalaops.example.com'),
        // Shown on invoices for bank transfers; left out when empty.
        'iban' => env('SELLER_IBAN'),
        'bic' => env('SELLER_BIC'),
    ],

];
