<?php

// Finnish messages for the validation rules used by the API. Rules not
// listed here fall back to Laravel's English messages.
return [
    'after_or_equal' => 'Kentän :attribute on oltava sama tai myöhempi päivä kuin :date.',
    'between' => [
        'min' => [
            'string' => 'Kentän :attribute on oltava vähintään :min merkkiä pitkä.',
        ],
        'numeric' => 'Kentän :attribute on oltava välillä :min–:max.',
    ],
    'date_format' => 'Kentän :attribute on oltava muodossa :format.',
    'enum' => 'Kentän :attribute arvo on virheellinen.',
    'in' => 'Kentän :attribute arvo on virheellinen.',
    'integer' => 'Kentän :attribute on oltava kokonaisluku.',
    'max' => [
        'string' => 'Kenttä :attribute saa olla enintään :max merkkiä pitkä.',
    ],
    'min' => [
        'numeric' => 'Kentän :attribute on oltava vähintään :min.',
        'string' => 'Kentän :attribute on oltava vähintään :min merkkiä pitkä.',
    ],
    'numeric' => 'Kentän :attribute on oltava numero.',
    'regex' => 'Kentän :attribute muoto on virheellinen.',
    'required' => 'Kenttä :attribute on pakollinen.',
    'prohibited_unless' => 'Kenttää :attribute ei voi antaa tässä tilassa.',
    'required_with' => 'Kenttä :attribute on pakollinen, kun :values on annettu.',
    'string' => 'Kentän :attribute on oltava tekstiä.',

    'attributes' => [
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
        'q' => 'haku',
        'search' => 'haku',
        'due' => 'määräpäivä',
        'due_from' => 'alkaen',
        'due_to' => 'asti',
        'signed_by' => 'kuittaajan nimi',
        'completion_signature' => 'allekirjoitus',
        'sort' => 'järjestys',
        'per_page' => 'sivun koko',
        'page' => 'sivu',
    ],
];
