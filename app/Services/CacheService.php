<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Advanced Cache Service with tagging and standardization
 *
 * Provides consistent caching strategy with:
 * - Cache tags for easy invalidation
 * - Standardized TTL values
 * - Remember vs rememberForever patterns
 * - Prefix-based key organization
 */
class CacheService
{
    /**
     * Cache TTL constants (in seconds)
     */
    const TTL_SHORT = 300;       // 5 minutes - volatile data

    const TTL_MEDIUM = 900;      // 15 minutes - semi-volatile

    const TTL_LONG = 3600;       // 1 hour - stable data

    const TTL_DAILY = 86400;     // 24 hours - rarely changing

    const TTL_WEEKLY = 604800;   // 7 days - static data

    /**
     * Cache tag groups
     */
    const TAG_DASHBOARD = 'dashboard';

    const TAG_MEMBERS = 'members';

    const TAG_LETTERS = 'letters';

    const TAG_FINANCE = 'finance';

    const TAG_UNITS = 'units';

    const TAG_POSITIONS = 'positions';

    const TAG_CATEGORIES = 'categories';

    /**
     * Remember cached data with tags
     *
     * @param  string  $key  Cache key
     * @param  int  $ttl  Time to live in seconds
     * @param  array  $tags  Cache tags for invalidation
     * @param  callable  $callback  Data generator callback
     * @return mixed
     */
    public static function remember(string $key, int $ttl, array $tags, callable $callback)
    {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Remember cached data forever (with tags for manual invalidation)
     *
     * @param  string  $key  Cache key
     * @param  array  $tags  Cache tags for invalidation
     * @param  callable  $callback  Data generator callback
     * @return mixed
     */
    public static function rememberForever(string $key, array $tags, callable $callback)
    {
        return Cache::tags($tags)->rememberForever($key, $callback);
    }

    /**
     * Flush cache by tag(s)
     *
     * @param  string|array  $tags  Single tag or array of tags
     */
    public static function flushTag($tags): bool
    {
        return Cache::tags((array) $tags)->flush();
    }

    /**
     * Get dashboard cache key with prefix
     */
    public static function dashboardKey(string $identifier, ?string $scope = null): string
    {
        $parts = ['dash', $identifier];
        if ($scope) {
            $parts[] = $scope;
        }

        return implode(':', $parts);
    }

    /**
     * Get members cache key with prefix
     */
    public static function membersKey(string $identifier, ?int $id = null): string
    {
        $parts = ['members', $identifier];
        if ($id) {
            $parts[] = (string) $id;
        }

        return implode(':', $parts);
    }

    /**
     * Get letters cache key with prefix
     */
    public static function lettersKey(string $identifier, ?int $id = null): string
    {
        $parts = ['letters', $identifier];
        if ($id) {
            $parts[] = (string) $id;
        }

        return implode(':', $parts);
    }

    /**
     * Get finance cache key with prefix
     */
    public static function financeKey(string $identifier, ?int $unitId = null): string
    {
        $parts = ['finance', $identifier];
        if ($unitId) {
            $parts[] = "unit_{$unitId}";
        }

        return implode(':', $parts);
    }

    /**
     * Flush all dashboard caches
     */
    public static function flushDashboard(): bool
    {
        return self::flushTag(self::TAG_DASHBOARD);
    }

    /**
     * Flush all member caches
     */
    public static function flushMembers(): bool
    {
        return self::flushTag(self::TAG_MEMBERS);
    }

    /**
     * Flush all letter caches
     */
    public static function flushLetters(): bool
    {
        return self::flushTag(self::TAG_LETTERS);
    }

    /**
     * Flush all finance caches
     */
    public static function flushFinance(): bool
    {
        return self::flushTag(self::TAG_FINANCE);
    }

    /**
     * Flush all reference data caches (units, positions, categories)
     */
    public static function flushReferences(): bool
    {
        return self::flushTag([self::TAG_UNITS, self::TAG_POSITIONS, self::TAG_CATEGORIES]);
    }

    /**
     * Flush all application caches (use with caution)
     */
    public static function flushAll(): bool
    {
        return Cache::flush();
    }

    /**
     * Pre-warm critical caches
     * Call this during deployment or off-peak hours
     */
    public static function warmup(): array
    {
        $results = [];

        // Warm up reference data
        try {
            $units = \App\Models\OrganizationUnit::select('id', 'name', 'code')->get();
            Cache::tags([self::TAG_UNITS])->rememberForever('units:all', fn () => $units);
            $results['units'] = 'warmed';
        } catch (\Exception $e) {
            $results['units'] = 'failed: '.$e->getMessage();
        }

        // Warm up positions
        try {
            $positions = \App\Models\UnionPosition::select('id', 'name')->get();
            Cache::tags([self::TAG_POSITIONS])->rememberForever('positions:all', fn () => $positions);
            $results['positions'] = 'warmed';
        } catch (\Exception $e) {
            $results['positions'] = 'failed: '.$e->getMessage();
        }

        // Warm up letter categories
        try {
            $categories = \App\Models\LetterCategory::active()->ordered()->get(['id', 'name', 'code']);
            Cache::tags([self::TAG_CATEGORIES])->rememberForever('letter_categories:active', fn () => $categories);
            $results['categories'] = 'warmed';
        } catch (\Exception $e) {
            $results['categories'] = 'failed: '.$e->getMessage();
        }

        return $results;
    }
}
