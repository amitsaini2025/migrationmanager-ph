<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Action-page task_group values and display labels.
 * Stored DB values stay stable for filters/validation; Type column can show shorter labels.
 */
class ActionTaskGroup
{
    public const EOI_AMENDMENT = 'EOI/ROI Amendment';

    public const EOI_CONFIRMATION = 'EOI/ROI Confirmation';

    public const FOLLOW_UP = 'Follow Up';

    /**
     * @return list<string>
     */
    public static function eoiRoiGroups(): array
    {
        return [self::EOI_AMENDMENT, self::EOI_CONFIRMATION];
    }

    /**
     * Groups where assigner can be the client (client-initiated portal/EOI actions).
     *
     * @return list<string>
     */
    public static function clientInitiatedAssignerGroups(): array
    {
        return array_merge(['Client Portal'], self::eoiRoiGroups());
    }

    public static function displayLabel(?string $taskGroup): string
    {
        return match ((string) $taskGroup) {
            self::EOI_AMENDMENT => 'Amendment',
            self::EOI_CONFIRMATION => 'Confirmation',
            '' => 'N/P',
            default => (string) $taskGroup,
        };
    }

    public static function isFollowUp(?string $taskGroup): bool
    {
        return (string) $taskGroup === self::FOLLOW_UP;
    }

    public static function assignActivitySubject(string $assigneeName, ?string $taskGroup): string
    {
        $prefix = self::isFollowUp($taskGroup) ? 'Set followup for ' : 'Set action for ';

        return $prefix.$assigneeName;
    }

    /**
     * Follow-ups appear from 1 day before assign date, so the latest visible assign date is tomorrow.
     */
    public static function latestVisibleFollowUpAssignDate(?DateTimeInterface $today = null): string
    {
        $day = $today ? Carbon::parse($today)->startOfDay() : now()->startOfDay();

        return $day->addDay()->toDateString();
    }

    public static function followUpIsVisibleOnActionPage(?string $assignDate, ?DateTimeInterface $today = null): bool
    {
        if ($assignDate === null || trim($assignDate) === '') {
            return false;
        }

        try {
            $assign = Carbon::parse($assignDate)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $todayStart = $today ? Carbon::parse($today)->startOfDay() : now()->startOfDay();

        return $todayStart->greaterThanOrEqualTo($assign->copy()->subDay());
    }

    /**
     * Restrict a Follow Up tab query to rows whose assign date is due (from 1 day before).
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function constrainToVisibleFollowUpDates($query, string $actionDateColumn = 'action_date'): void
    {
        $query->whereDate($actionDateColumn, '<=', self::latestVisibleFollowUpAssignDate());
    }

    /**
     * On the All tab, hide Follow Up rows that are more than 1 day before their assign date.
     * Other action types are unchanged.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function hideFollowUpsNotYetDue($query, string $taskGroupColumn = 'task_group', string $actionDateColumn = 'action_date'): void
    {
        $latest = self::latestVisibleFollowUpAssignDate();

        $query->where(function ($inner) use ($taskGroupColumn, $actionDateColumn, $latest) {
            $inner->where($taskGroupColumn, '!=', self::FOLLOW_UP)
                ->orWhereNull($taskGroupColumn)
                ->orWhere(function ($followUpQuery) use ($taskGroupColumn, $actionDateColumn, $latest) {
                    $followUpQuery->where($taskGroupColumn, self::FOLLOW_UP)
                        ->whereDate($actionDateColumn, '<=', $latest);
                });
        });
    }
}
