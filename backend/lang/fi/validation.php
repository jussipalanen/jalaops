<?php

// Finnish messages for the validation rules used by the API. Rules not
// listed here fall back to Laravel's English messages.
return [
    'date_format' => 'Kentän :attribute on oltava muodossa :format.',
    'enum' => 'Kentän :attribute arvo on virheellinen.',
    'max' => [
        'string' => 'Kenttä :attribute saa olla enintään :max merkkiä pitkä.',
    ],
    'required' => 'Kenttä :attribute on pakollinen.',
    'string' => 'Kentän :attribute on oltava tekstiä.',

    'attributes' => [
        'title' => 'otsikko',
        'description' => 'kuvaus',
        'priority' => 'prioriteetti',
        'status' => 'tila',
        'due_date' => 'määräpäivä',
    ],
];
