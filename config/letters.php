<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SLA Hours by Urgency Level
    |--------------------------------------------------------------------------
    |
    | Define the number of hours allowed for letter approval based on urgency.
    | - biasa: Standard letters (72 hours = 3 days)
    | - segera: Urgent letters (24 hours = 1 day)
    | - kilat: Immediate priority (4 hours)
    |
    */
    'sla_hours_by_urgency' => [
        'biasa' => 72,
        'segera' => 24,
        'kilat' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default SLA Hours
    |--------------------------------------------------------------------------
    |
    | Fallback SLA hours if urgency is not recognized.
    |
    */
    'default_sla_hours' => 72,

    /*
    |--------------------------------------------------------------------------
    | SLA Status Values
    |--------------------------------------------------------------------------
    |
    | Possible values for sla_status column.
    |
    */
    'sla_statuses' => [
        'ok' => 'ok',
        'breach' => 'breach',
    ],
];
