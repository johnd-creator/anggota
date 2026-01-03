<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Dues Amount
    |--------------------------------------------------------------------------
    |
    | The default monthly dues amount in IDR.
    |
    */
    'default_amount' => env('DUES_DEFAULT_AMOUNT', 30000),

    /*
    |--------------------------------------------------------------------------
    | Due Day
    |--------------------------------------------------------------------------
    |
    | The day of the month by which dues should be paid.
    |
    */
    'due_day' => env('DUES_DUE_DAY', 10),

    /*
    |--------------------------------------------------------------------------
    | Generate On Day
    |--------------------------------------------------------------------------
    |
    | The day of the month when dues records are auto-generated.
    |
    */
    'generate_on_day' => env('DUES_GENERATE_ON_DAY', 1),
];
