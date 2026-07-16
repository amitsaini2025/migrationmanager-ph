<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Safely removes only GenerateServiceAccountToken jobs from Redis + failed_jobs.
 * Does not delete mail / other queue jobs.
 */
class ClearStuckServiceAccountTokenJobs extends Command
{
    protected $signature = 'queue:clear-service-account-token-jobs
                            {--connection=redis : Queue connection name}
                            {--queue=default : Queue name}
                            {--dry-run : Show counts only, do not delete}';

    protected $description = 'Remove stuck GenerateServiceAccountToken jobs from Redis and failed_jobs (keeps email jobs)';

    private const JOB_CLASS = 'App\\Jobs\\GenerateServiceAccountToken';
    private const JOB_CLASS_NEEDLE = 'GenerateServiceAccountToken';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $connection = (string) $this->option('connection');
        $queue = (string) $this->option('queue');

        $failedRemoved = $this->clearFailedJobs($dryRun);
        $redisRemoved = $this->clearRedisJobs($connection, $queue, $dryRun);

        $this->info(($dryRun ? '[dry-run] Would remove' : 'Removed') . " {$failedRemoved} failed_jobs row(s).");
        $this->info(($dryRun ? '[dry-run] Would remove' : 'Removed') . " {$redisRemoved} Redis queue job(s).");
        $this->line('Other jobs (emails, Hubdoc, etc.) were left untouched.');

        if (!$dryRun) {
            $this->comment('Restart workers if needed: php artisan queue:restart');
        }

        return self::SUCCESS;
    }

    private function clearFailedJobs(bool $dryRun): int
    {
        if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            return 0;
        }

        // Match on class short name — avoids SQL backslash-escape issues with App\Jobs\...
        $query = DB::table('failed_jobs')->where('payload', 'like', '%' . self::JOB_CLASS_NEEDLE . '%');
        $count = (int) $query->count();

        if (!$dryRun && $count > 0) {
            DB::table('failed_jobs')->where('payload', 'like', '%' . self::JOB_CLASS_NEEDLE . '%')->delete();
        }

        return $count;
    }

    private function clearRedisJobs(string $connection, string $queue, bool $dryRun): int
    {
        if ($connection !== 'redis') {
            $this->warn("Skipping Redis scan for connection [{$connection}] (only redis is supported by this command).");
            return 0;
        }

        try {
            $redis = Redis::connection();
        } catch (\Throwable $e) {
            $this->error('Could not connect to Redis: ' . $e->getMessage());
            return 0;
        }

        $removed = 0;
        foreach ($this->redisQueueKeys($queue) as $key) {
            $removed += $this->filterRedisList($redis, $key, $dryRun);
        }

        return $removed;
    }

    /**
     * @return list<string>
     */
    private function redisQueueKeys(string $queue): array
    {
        return [
            "queues:{$queue}",
            "queues:{$queue}:delayed",
            "queues:{$queue}:reserved",
            "queues:{$queue}:notify",
        ];
    }

    private function filterRedisList($redis, string $key, bool $dryRun): int
    {
        $type = $redis->type($key);
        // Predis may return string "list"; PhpRedis may return int
        $isList = $type === 'list' || $type === 1;
        if (!$isList) {
            return 0;
        }

        $items = $redis->lrange($key, 0, -1);
        if (empty($items)) {
            return 0;
        }

        $keep = [];
        $removed = 0;

        foreach ($items as $item) {
            if ($this->payloadIsServiceAccountTokenJob($item)) {
                $removed++;
                continue;
            }
            $keep[] = $item;
        }

        if ($dryRun || $removed === 0) {
            return $removed;
        }

        $redis->del($key);
        foreach ($keep as $item) {
            $redis->rpush($key, $item);
        }

        return $removed;
    }

    private function payloadIsServiceAccountTokenJob(string $raw): bool
    {
        if (str_contains($raw, self::JOB_CLASS_NEEDLE)) {
            return true;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return false;
        }

        $displayName = $decoded['displayName'] ?? '';
        if (is_string($displayName) && str_contains($displayName, self::JOB_CLASS_NEEDLE)) {
            return true;
        }

        $commandName = $decoded['data']['commandName'] ?? '';
        if (is_string($commandName) && str_contains($commandName, self::JOB_CLASS_NEEDLE)) {
            return true;
        }

        $command = $decoded['data']['command'] ?? '';
        return is_string($command) && str_contains($command, self::JOB_CLASS_NEEDLE);
    }
}
