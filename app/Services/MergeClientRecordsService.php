<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MergeClientRecordsService
{
    /**
     * Re-point related rows from the source CRM record onto the survivor, then leave
     * the caller to soft-delete the source. Duplicate unique rows stay on the source.
     */
    public function move(int $fromId, int $intoId): void
    {
        if ($fromId <= 0 || $intoId <= 0 || $fromId === $intoId) {
            return;
        }

        foreach (['client_id', 'lead_id', 'nominated_client_id'] as $column) {
            foreach ($this->tablesHavingColumn($column) as $table) {
                $this->reassign($table, $column, $fromId, $intoId);
            }
        }

        foreach (['companies' => 'admin_id', 'client_access_grants' => 'admin_id'] as $table => $column) {
            $this->reassign($table, $column, $fromId, $intoId);
        }

        $this->moveTagsAndRelatedFiles($fromId, $intoId);
    }

    /**
     * @return list<string>
     */
    private function tablesHavingColumn(string $column): array
    {
        $matched = [];

        foreach ($this->listTables() as $table) {
            if (in_array($table, $this->skippedTables(), true)) {
                continue;
            }

            try {
                if (Schema::hasColumn($table, $column)) {
                    $matched[] = $table;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $matched;
    }

    /**
     * @return list<string>
     */
    private function listTables(): array
    {
        $connection = Schema::getConnection();
        $builder = $connection->getSchemaBuilder();

        if (method_exists($builder, 'getTableListing')) {
            return array_values(array_filter(
                $builder->getTableListing(),
                static fn ($table) => is_string($table) && $table !== ''
            ));
        }

        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
        }

        if ($driver === 'pgsql') {
            return collect(DB::select(
                'SELECT tablename AS name FROM pg_tables WHERE schemaname = current_schema()'
            ))->pluck('name')->filter()->values()->all();
        }

        return collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function skippedTables(): array
    {
        return [
            'admins',
            'migrations',
            'staff',
            'jobs',
            'failed_jobs',
            'job_batches',
            'cache',
            'cache_locks',
            'sessions',
            'password_resets',
            'password_reset_tokens',
            'personal_access_tokens',
        ];
    }

    private function reassign(string $table, string $column, int $fromId, int $intoId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            DB::table($table)->where($column, $fromId)->update([$column => $intoId]);
        } catch (Throwable) {
            $this->reassignRowByRow($table, $column, $fromId, $intoId);
        }
    }

    private function reassignRowByRow(string $table, string $column, int $fromId, int $intoId): void
    {
        if (! Schema::hasColumn($table, 'id')) {
            return;
        }

        $ids = DB::table($table)->where($column, $fromId)->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::table($table)->where('id', $id)->update([$column => $intoId]);
            } catch (Throwable) {
                // Unique conflict: keep the survivor's row and leave this one on the source.
            }
        }
    }

    private function moveTagsAndRelatedFiles(int $fromId, int $intoId): void
    {
        $this->mergeCommaSeparatedAdminField('tagname', $fromId, $intoId, false);
        $this->mergeCommaSeparatedAdminField('related_files', $fromId, $intoId, true);
        $this->retargetRelatedFileLinks($fromId, $intoId);
    }

    private function mergeCommaSeparatedAdminField(string $column, int $fromId, int $intoId, bool $excludeRecordIds): void
    {
        if (! Schema::hasColumn('admins', $column)) {
            return;
        }

        $fromValue = DB::table('admins')->where('id', $fromId)->value($column);
        $intoValue = DB::table('admins')->where('id', $intoId)->value($column);
        $merged = array_values(array_unique(array_merge(
            $this->csvToList($intoValue),
            $this->csvToList($fromValue)
        )));

        if ($excludeRecordIds) {
            $merged = array_values(array_filter(
                $merged,
                static fn ($id) => (int) $id !== $fromId && (int) $id !== $intoId
            ));
        }

        DB::table('admins')->where('id', $intoId)->update([
            $column => $merged === [] ? null : implode(',', $merged),
        ]);
        DB::table('admins')->where('id', $fromId)->update([$column => null]);
    }

    private function retargetRelatedFileLinks(int $fromId, int $intoId): void
    {
        if (! Schema::hasColumn('admins', 'related_files')) {
            return;
        }

        $from = (string) $fromId;
        $linked = DB::table('admins')
            ->where('id', '!=', $fromId)
            ->whereNotNull('related_files')
            ->where('related_files', '!=', '')
            ->where(function ($query) use ($from) {
                $query->where('related_files', $from)
                    ->orWhere('related_files', 'like', $from.',%')
                    ->orWhere('related_files', 'like', '%,'.$from)
                    ->orWhere('related_files', 'like', '%,'.$from.',%');
            })
            ->get(['id', 'related_files']);

        foreach ($linked as $row) {
            $ids = $this->csvToList($row->related_files);
            $ids = array_map(static fn ($id) => (int) $id === $fromId ? (string) $intoId : $id, $ids);
            $ids = array_values(array_unique(array_filter(
                $ids,
                static fn ($id) => (int) $id !== $fromId && (int) $id !== (int) $row->id
            )));

            DB::table('admins')->where('id', $row->id)->update([
                'related_files' => $ids === [] ? null : implode(',', $ids),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function csvToList(mixed $csv): array
    {
        if ($csv === null) {
            return [];
        }

        $parts = array_map('trim', explode(',', (string) $csv));

        return array_values(array_filter($parts, static fn ($part) => $part !== ''));
    }
}
