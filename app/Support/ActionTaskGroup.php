<?php

namespace App\Support;

/**
 * Action-page task_group values and display labels.
 * Stored DB values stay stable for filters/validation; Type column can show shorter labels.
 */
class ActionTaskGroup
{
    public const EOI_AMENDMENT = 'EOI/ROI Amendment';

    public const EOI_CONFIRMATION = 'EOI/ROI Confirmation';

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
}
