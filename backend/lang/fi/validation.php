<?php

// Finnish messages for the validation rules used by the API. Rules not
// listed here fall back to Laravel's English messages.
return [
    'between' => [
        'min' => [
            'string' => 'Kentän :attribute on oltava vähintään :min merkkiä pitkä.',
        ],
        'numeric' => 'Kentän :attribute on oltava välillä :min–:max.',
    ],
    'date_format' => 'Kentän :attribute on oltava muodossa :format.',
    'enum' => 'Kentän :attribute arvo on virheellinen.',
    'max' => [
        'string' => 'Kenttä :attribute saa olla enintään :max merkkiä pitkä.',
    ],
    'min' => [
        'string' => 'Kentän :attribute on oltava vähintään :min merkkiä pitkä.',
    ],
    'numeric' => 'Kentän :attribute on oltava numero.',
    'regex' => 'Kentän :attribute muoto on virheellinen.',
    'required' => 'Kenttä :attribute on pakollinen.',
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
    ],
];
