<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that gets used while
    | using this caching library. This connection is used when another is
    | not explicitly specified when executing a given caching function.
    |
    | Supported: "apc", "array", "database", "file", "memcached", "redis"
    |
    */

    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    */

    'stores' => [

        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT  => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard client matters list (COUNT for paginator)
    |--------------------------------------------------------------------------
    |
    | TTL in seconds for the filtered total used by LengthAwarePaginator.
    | Short TTL limits stale totals after creates/deletes or filter changes.
    |
    */

    'dashboard_client_matters_count_ttl' => (int) env('DASHBOARD_CLIENT_MATTERS_COUNT_TTL', 45),

    /*
    |--------------------------------------------------------------------------
    | Dashboard KPI counts (note deadlines, cases requiring attention)
    |--------------------------------------------------------------------------
    */

    'dashboard_kpi_counts_ttl' => (int) env('DASHBOARD_KPI_COUNTS_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | CRM topbar badge counts (notifications, booking pending)
    |--------------------------------------------------------------------------
    */

    'header_counts_ttl' => (int) env('HEADER_COUNTS_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a RAM based store such as APC or Memcached, there might
    | be other applications utilizing the same cache. So, we'll specify a
    | value to get prefixed to all our keys so we can avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | When non-false, only the listed classes may be unserialized from cache
    | (Laravel 13+). Set to an array of class names if you store objects in
    | cache; keep false for maximum safety (default).
    |
    */

    'serializable_classes' => false,

];
