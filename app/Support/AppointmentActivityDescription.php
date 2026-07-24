<?php

namespace App\Support;

use App\Models\BookingAppointment;
use Carbon\Carbon;

/**
 * Builds activity feed subject + description HTML for booking appointments.
 */
class AppointmentActivityDescription
{
    /**
     * @var array<int, string>
     */
    private const NOE_LABELS = [
        1 => 'GSM Visas: 491, 190, 189, 191',
        2 => 'TR: 485 visa',
        3 => 'JRP/Skill Assessment',
        4 => 'Tourist Visa',
        5 => 'Education/Course Change/Student Visa/Student Dependent Visa (for education selection for Australian onshore clients only)',
        6 => 'Complex matters: ART, Protection visa, Federal Case',
        7 => 'Visa Cancellation/ NOICC/ Visa refusals',
        8 => 'Anyone who is outside Australia',
        9 => 'EOI/ROI',
        10 => 'Employer Sponsored Visas: 494, 482, 186, DAMA',
        11 => 'Family Visas (Parent Visa, Partner Visa, Child Visa)',
        12 => 'Citizenship',
    ];

    public static function activitySubject(?int $serviceId): string
    {
        return match ((int) $serviceId) {
            2 => 'scheduled a free appointment',
            1, 3 => 'scheduled a paid appointment',
            default => 'scheduled an appointment',
        };
    }

    public static function serviceTitle(?int $serviceId): string
    {
        return match ((int) $serviceId) {
            2 => 'Free Consultation',
            1 => 'Comprehensive Migration Advice',
            3 => 'Overseas Applicant Enquiry',
            default => 'Appointment',
        };
    }

    public static function categoryLabel(BookingAppointment $appointment, ?int $noeId = null): string
    {
        $fromModel = trim((string) ($appointment->service_type ?? ''));
        if ($fromModel !== '') {
            return $fromModel;
        }

        $id = $noeId ?? $appointment->noe_id;

        return self::NOE_LABELS[(int) $id] ?? 'Appointment';
    }

    public static function meetingTypeLabel(?string $meetingType): ?string
    {
        return match (strtolower((string) $meetingType)) {
            'in_person' => 'In Person',
            'phone' => 'Phone',
            'video' => 'Video Call',
            default => null,
        };
    }

    public static function locationLabel(BookingAppointment $appointment): ?string
    {
        if (! $appointment->location) {
            return null;
        }

        $location = strtolower((string) $appointment->location);

        if ($location === 'adelaide' && (int) $appointment->service_id === 2) {
            return 'Adelaide Free PR';
        }

        if ($location === 'melbourne' && (int) $appointment->service_id === 2) {
            return 'Melbourne Free PR';
        }

        return ucfirst($location);
    }

    public static function buildDescription(BookingAppointment $appointment, ?int $noeId = null): string
    {
        $serviceId = $appointment->service_id;
        $badgeDate = self::badgeDateString($appointment);
        $category = self::categoryLabel($appointment, $noeId);
        $apptTypeParts = array_filter([
            self::serviceTitle($serviceId),
            self::meetingTypeLabel($appointment->meeting_type),
        ]);
        $apptType = implode(' · ', $apptTypeParts);
        $query = trim((string) ($appointment->enquiry_details ?? ''));
        $dateTime = self::dateTimeDisplay($appointment);
        $language = trim((string) ($appointment->preferred_language ?? ''));
        $location = self::locationLabel($appointment);

        $rows = '<div class="appointment-activity-detail__chip">Appointment</div>';
        $rows .= self::detailRow('Category:', $category);
        $rows .= self::detailRow('Appt. Type:', $apptType);
        if ($query !== '') {
            $rows .= self::detailRow('Query:', $query);
        }
        if ($dateTime !== null && $dateTime !== '') {
            $rows .= self::detailRow('Date & Time:', $dateTime, 'datetime');
        }
        if ($language !== '') {
            $rows .= self::detailRow('Language:', $language);
        }
        if ($location !== null && $location !== '') {
            $rows .= self::detailRow('Location:', $location);
        }

        return '<div class="appointment-activity-detail">'
            . self::dateBadgeHtml($badgeDate)
            . '<div class="appointment-activity-detail__body">' . $rows . '</div>'
            . '</div>';
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildActivityLogPayload(
        BookingAppointment $appointment,
        int $createdBy,
        ?int $serviceId = null,
        ?int $noeId = null
    ): array {
        $resolvedServiceId = $serviceId ?? $appointment->service_id;

        return [
            'client_id' => $appointment->client_id,
            'created_by' => $createdBy,
            'subject' => self::activitySubject($resolvedServiceId),
            'description' => self::buildDescription($appointment, $noeId),
            'activity_type' => 'activity',
            'task_status' => 0,
            'pin' => 0,
        ];
    }

    private static function detailRow(string $label, string $value, ?string $field = null): string
    {
        if ($value === '') {
            return '';
        }

        $rowClass = 'appointment-activity-detail__row';
        $fieldAttr = '';

        if ($field !== null && $field !== '') {
            $rowClass .= ' appointment-activity-detail__row--'.$field;
            $fieldAttr = ' data-field="'.e($field).'"';
        }

        return '<div class="'.$rowClass.'"'.$fieldAttr.'>'
            . '<span class="appointment-activity-detail__label">'.e($label).'</span> '
            . '<span class="appointment-activity-detail__value">'.e($value).'</span>'
            . '</div>';
    }

    private static function badgeDateString(BookingAppointment $appointment): string
    {
        $appointmentDate = $appointment->appointment_datetime;

        if ($appointmentDate instanceof Carbon) {
            return $appointmentDate->format('Y-m-d');
        }

        if ($appointmentDate) {
            return Carbon::parse($appointmentDate)->format('Y-m-d');
        }

        return date('Y-m-d');
    }

    private static function dateTimeDisplay(BookingAppointment $appointment): ?string
    {
        $appointmentDate = $appointment->appointment_datetime;
        $datePart = null;

        if ($appointmentDate instanceof Carbon) {
            $datePart = $appointmentDate->format('d M Y');
        } elseif ($appointmentDate) {
            $datePart = Carbon::parse($appointmentDate)->format('d M Y');
        }

        $timePart = trim((string) ($appointment->timeslot_full ?? ''));

        if ($timePart === '' && $appointmentDate) {
            if ($appointmentDate instanceof Carbon) {
                $timePart = $appointmentDate->format('h:i A');
            } else {
                $timePart = Carbon::parse($appointmentDate)->format('h:i A');
            }
        }

        if ($datePart && $timePart) {
            return $datePart . ' · ' . $timePart;
        }

        return $datePart ?: ($timePart ?: null);
    }

    private static function dateBadgeHtml(string $activityLogDate): string
    {
        $dayMonth = date('d M', strtotime($activityLogDate));
        $year = date('Y', strtotime($activityLogDate));

        return '<div class="appointment-activity-detail__badge">'
            . '<span class="appointment-activity-detail__badge-inner">'
            . '<span class="appointment-activity-detail__badge-day">' . e($dayMonth) . '</span>'
            . '<span class="appointment-activity-detail__badge-year">' . e($year) . '</span>'
            . '</span>'
            . '</div>';
    }
}
