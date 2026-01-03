<?php

/**
 * Feature Flags Configuration
 *
 * Global feature toggles for maintenance/incident response.
 * Set to false to disable a feature's routes (returns 503).
 *
 * Usage:
 *   - Route middleware: ->middleware('feature:announcements')
 *   - Direct check: config('features.announcements')
 *
 * To disable a feature in production:
 *   1. Set FEATURE_ANNOUNCEMENTS=false in .env
 *   2. Run: php artisan config:cache (or config:clear)
 *   3. Routes will return 503 Service Unavailable
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Announcements Feature
    |--------------------------------------------------------------------------
    |
    | Controls access to /announcements (public) and /admin/announcements.
    |
    */
    'announcements' => env('FEATURE_ANNOUNCEMENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Letters Feature
    |--------------------------------------------------------------------------
    |
    | Controls access to /letters (inbox, outbox, approvals, etc).
    |
    */
    'letters' => env('FEATURE_LETTERS', true),

    /*
    |--------------------------------------------------------------------------
    | Finance Feature
    |--------------------------------------------------------------------------
    |
    | Controls access to /finance (dues, ledgers, categories).
    |
    */
    'finance' => env('FEATURE_FINANCE', true),
];
