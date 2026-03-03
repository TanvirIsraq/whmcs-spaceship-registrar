<?php

namespace Spaceship;

use WHMCS\Database\Capsule;

class Cache
{
    /**
     * Database table name for caching
     */
    const TABLE = 'mod_spaceship_cache';

    /**
     * Initialize the caching table if it doesn't exist.
     *
     * @return void
     */
    public static function init()
    {
        try {
            if (!\WHMCS\Database\Capsule::schema()->hasTable(self::TABLE)) {
                \WHMCS\Database\Capsule::schema()->create(
                    self::TABLE,
                    function ($table) {
                        $table->increments('id');
                        $table->string('domain_name');
                        $table->string('cache_key');
                        $table->text('cache_value');
                        $table->integer('expires_at'); // Unix Timestamp for timezone safety
                        $table->index(['domain_name', 'cache_key']);
                    }
                );
            }
        } catch (\Throwable $e) {
            if (\function_exists('logActivity')) {
                \logActivity("Spaceship Cache Init Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Retrieve a value from the database cache.
     * Implements Lazy Initialization: If table is missing, attempts to create it.
     *
     * @param string $domain
     * @param string $key
     * @return mixed|null
     */
    public static function get($domain, $key)
    {
        try {
            // Lazy Init: Ensure table exists
            if (!\WHMCS\Database\Capsule::schema()->hasTable(self::TABLE)) {
                self::init();
            }

            $cache = \WHMCS\Database\Capsule::table(self::TABLE)
                ->where('domain_name', $domain)
                ->where('cache_key', $key)
                ->where('expires_at', '>', \time())
                ->first();

            return $cache ? \json_decode($cache->cache_value, true) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Save a value to the database cache.
     *
     * @param string $domain
     * @param string $key
     * @param mixed $value
     * @param int $ttl Seconds until expiry (default 300 / 5 minutes)
     * @return void
     */
    public static function set($domain, $key, $value, $ttl = 300)
    {
        try {
            $expiresAt = \time() + $ttl;

            \WHMCS\Database\Capsule::table(self::TABLE)->updateOrInsert(
                ['domain_name' => $domain, 'cache_key' => $key],
                ['cache_value' => \json_encode($value), 'expires_at' => $expiresAt]
            );
        } catch (\Throwable $e) {
            // Fail silently to allow API-only operation if DB fails
        }
    }

    /**
     * Clear the cache for a domain (used after update actions).
     *
     * @param string $domain
     * @return void
     */
    public static function clear($domain)
    {
        try {
            \WHMCS\Database\Capsule::table(self::TABLE)->where('domain_name', $domain)->delete();
        } catch (\Throwable $e) {
            // Fail silently
        }
    }
}
