<?php

// Finnish messages for the validation rules used by the API. Rules not
// listed here fall back to Laravel's English messages.
return [
    'after_or_equal' => 'Kentän :attribute on oltava sama tai myöhempi päivä kuin :date.',
    'between' => [
        'integer' => 'Kentän :attribute on oltava välillä :min–:max.',
        'numeric' => 'Kentän :attribute on oltava välillä :min–:max.',
    ],
    'date_format' => 'Kentän :attribute on oltava muodossa :format.',
    // Every decimal rule in the API allows at most two decimals (decimal:0,2).
    'decimal' => 'Kentässä :attribute saa olla enintään kaksi desimaalia.',
    'email' => 'Kentän :attribute on oltava sähköpostiosoite.',
    'enum' => 'Kentän :attribute arvo on virheellinen.',
    'exists' => 'Valittua kohdetta (:attribute) ei löytynyt.',
    'file' => 'Kentän :attribute on oltava tiedosto.',
    'gt' => [
        'numeric' => 'Kentän :attribute on oltava suurempi kuin :value.',
    ],
    'in' => 'Kentän :attribute arvo on virheellinen.',
    'integer' => 'Kentän :attribute on oltava kokonaisluku.',
    'max' => [
        'numeric' => 'Kenttä :attribute saa olla enintään :max.',
        'file' => 'Tiedosto saa olla enintään :max kilotavua.',
        'string' => 'Kenttä :attribute saa olla enintään :max merkkiä pitkä.',
    ],
    'mimes' => 'Tiedoston on oltava tyyppiä :values.',
    'min' => [
        'numeric' => 'Kentän :attribute on oltava vähintään :min.',
        'string' => 'Kentän :attribute on oltava vähintään :min merkkiä pitkä.',
    ],
    'numeric' => 'Kentän :attribute on oltava numero.',
    'prohibited_unless' => 'Kenttää :attribute ei voi antaa tässä tilassa.',
    'regex' => 'Kentän :attribute muoto on virheellinen.',
    'required' => 'Kenttä :attribute on pakollinen.',
    'required_with' => 'Kenttä :attribute on pakollinen, kun :values on annettu.',
    'string' => 'Kentän :attribute on oltava tekstiä.',

    'attributes' => [
        // Requests
        'title' => 'otsikko',
        'description' => 'kuvaus',
        'priority' => 'prioriteetti',
        'status' => 'tila',
        'due_date' => 'määräpäivä',
        'street_address' => 'katuosoite',
        'postal_code' => 'postinumero',
        'city' => 'kaupunki',
        'latitude' => 'leveysaste',
        'longitude' => 'pituusaste',
        'signed_by' => 'kuittaajan nimi',
        'completion_signature' => 'allekirjoitus',
        'customer_id' => 'asiakas',

        // List filters, search and export
        'q' => 'haku',
        'search' => 'haku',
        'due' => 'määräpäivä',
        'due_from' => 'alkaen',
        'due_to' => 'asti',
        'customer' => 'asiakas',
        'sort' => 'järjestys',
        'per_page' => 'sivun koko',
        'page' => 'sivu',
        'format' => 'muoto',
        'ids' => 'valitut pyynnöt',
        'file' => 'tiedosto',

        // Work and materials
        'type' => 'rivin tyyppi',
        'quantity' => 'määrä',
        'unit' => 'yksikkö',
        'unit_price' => 'yksikköhinta',
        'vat_rate' => 'ALV-prosentti',

        // Customers
        'name' => 'nimi',
        'business_id' => 'y-tunnus',
        'contact_person' => 'yhteyshenkilö',
        'email' => 'sähköposti',
        'phone' => 'puhelin',
        'billing_street_address' => 'laskutusosoite',
        'billing_postal_code' => 'postinumero',
        'billing_city' => 'kaupunki',
        'payment_terms_days' => 'maksuehto',
        'notes' => 'muistiinpanot',
    ],
];
