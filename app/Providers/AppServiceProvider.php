<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\SortableHelper;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Paginator::useBootstrap();

        // Register sortable link directive
        Blade::directive('sortablelink', function ($expression) {
            return "<?php echo App\\Helpers\\SortableHelper::linkWithIcon($expression); ?>";
        });

        // Lucide icon directive — @icon('trash-2') or @icon('fa-trash', ['class' => 'icon-sm'])
        Blade::directive('icon', function ($expression) {
            return "<?php echo App\\Helpers\\IconHelper::render($expression); ?>";
        });
        
        // TIER 1 OPTIMIZATION: Query logging for slow query detection
        // Only enable in local/staging environments or when debugging
        if (config('app.debug') || env('LOG_SLOW_QUERIES', false)) {
            DB::listen(function ($query) {
                $slowQueryThreshold = env('SLOW_QUERY_THRESHOLD', 1000); // milliseconds
                
                if ($query->time > $slowQueryThreshold) {
                    Log::channel('daily')->warning('Slow Query Detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                        'location' => $this->getQueryLocation(),
                    ]);
                }
            });
        }
        
        // TIER 1 OPTIMIZATION: Log all queries in local environment (optional)
        if (env('LOG_ALL_QUERIES', false) && app()->environment('local')) {
            DB::listen(function ($query) {
                Log::channel('daily')->debug('Query Executed', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                ]);
            });
        }
    }

    /**
     * Get the location where the query was executed
     */
    protected function getQueryLocation()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        foreach ($trace as $item) {
            if (isset($item['file']) && !str_contains($item['file'], 'vendor')) {
                return $item['file'] . ':' . ($item['line'] ?? '?');
            }
        }
        
        return 'Unknown location';
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
