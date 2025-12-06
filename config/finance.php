<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Finance Workflow Toggle
    |--------------------------------------------------------------------------
    |
    | When enabled (true), transactions require approval from admin_unit.
    | Status flow: draft -> submitted -> approved/rejected
    | When disabled (false), all transactions are auto-approved.
    |
    */
    'workflow_enabled' => env('FINANCE_WORKFLOW', true),
];
