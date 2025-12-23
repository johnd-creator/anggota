<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Trail Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the unified audit logging system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Dual-Write Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, important events are written to both audit_logs and
    | activity_logs tables for backward compatibility during migration.
    |
    */
    'dual_write' => env('AUDIT_DUAL_WRITE', true),

    /*
    |--------------------------------------------------------------------------
    | Event Categories
    |--------------------------------------------------------------------------
    |
    | Mapping of event prefixes to categories for filtering and retention.
    |
    */
    'categories' => [
        // More specific prefixes first (order matters!)
        'login_failed' => 'auth_failed',
        'login_' => 'auth',
        'logout' => 'auth',
        'session_' => 'auth',
        'member.' => 'member',
        'mutation.' => 'mutation',
        'letter.' => 'surat',
        'dues.' => 'iuran',
        'ledger.' => 'iuran',
        'export.' => 'export',
        'import.' => 'export',
        'document.' => 'export',
        'onboarding_' => 'member',
        'audit_log.' => 'system',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Periods (in days)
    |--------------------------------------------------------------------------
    |
    | How long to keep audit logs before purging.
    |
    */
    'retention' => [
        'auth' => (int) env('AUDIT_RETENTION_AUTH', 180),
        'auth_failed' => (int) env('AUDIT_RETENTION_AUTH_FAILED', 90),
        'member' => (int) env('AUDIT_RETENTION_MEMBER', 365),
        'mutation' => (int) env('AUDIT_RETENTION_MUTATION', 365),
        'surat' => (int) env('AUDIT_RETENTION_SURAT', 365),
        'iuran' => (int) env('AUDIT_RETENTION_IURAN', 365),
        'export' => (int) env('AUDIT_RETENTION_EXPORT', 90),
        'default' => (int) env('AUDIT_RETENTION_DEFAULT', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Whitelist
    |--------------------------------------------------------------------------
    |
    | Fields allowed in the payload per event category.
    | Any field not in the whitelist will be stripped.
    |
    */
    'payload_whitelist' => [
        'auth' => [
            'email',
            'provider',
            'role_name',
            'google_id',
            'microsoft_id',
            'msg',
        ],
        'member' => [
            'member_id',
            'status',
            'old_status',
            'new_status',
            'unit_id',
            'nra',
            'reason',
            'count',
            'success_count',
            'failed_count',
        ],
        'mutation' => [
            'mutation_id',
            'from_unit_id',
            'to_unit_id',
            'effective_date',
            'reason',
            'status',
        ],
        'surat' => [
            'letter_id',
            'letter_type',
            'recipient_count',
            'approver_id',
            'status',
        ],
        'iuran' => [
            'ledger_id',
            'category_id',
            'amount_range',
            'status',
            'period',
        ],
        'export' => [
            'report_type',
            'format',
            'row_count',
            'filters',
            'filename',
        ],
        'auth_failed' => [
            'email',
            'provider',
            'reason',
        ],
        'system' => [
            'filters',
            'msg',
            'action',
            'count',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Forbidden Fields
    |--------------------------------------------------------------------------
    |
    | Fields that must NEVER be logged regardless of category.
    | These are stripped before any logging.
    |
    */
    'forbidden_fields' => [
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'secret',
        'private_key',
        'session_token',
        'cookie',
        'nik',
        'nip',
        'phone',
        'address',
        'alamat',
        'no_hp',
        'ktp',
        'bank_account',
        'rekening',
        'credit_card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Events to Skip
    |--------------------------------------------------------------------------
    |
    | Events that should not be logged (e.g., health checks, static assets).
    |
    */
    'skip_routes' => [
        'debugbar.*',
        'ignition.*',
        'sanctum.*',
        'livewire.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    |
    | Roles allowed to view audit logs.
    |
    */
    'view_roles' => ['super_admin'],
    'export_roles' => ['super_admin'],
];
