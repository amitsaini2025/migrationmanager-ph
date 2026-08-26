<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Collection;

/**
 * Active staff for Add Task / Create Task assignee pickers.
 *
 * Request-memoized so dashboard + action popover + modal loops share one query.
 */
final class AssigneeDropdownStaff
{
    /**
     * @return Collection<int, Staff>
     */
    public static function activeWithOffice(): Collection
    {
        return once(static function () {
            return Staff::query()
                ->select(['id', 'first_name', 'last_name', 'office_id', 'status'])
                ->where('status', 1)
                ->orderBy('first_name')
                ->with(['office:id,office_name'])
                ->get();
        });
    }
}
