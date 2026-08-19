<?php

namespace App\Support;

class AppointmentMeetingTypeCopy
{
    /**
     * @return 'phone'|'video'|'in_person'
     */
    public static function normalize(?string $meetingType): string
    {
        $type = strtolower(str_replace(['-', ' '], '_', trim((string) $meetingType)));

        return match ($type) {
            'phone', 'phone_call', 'phonecall' => 'phone',
            'video', 'video_call', 'videocall', 'zoom', 'online' => 'video',
            default => 'in_person',
        };
    }

    public static function label(?string $meetingType): string
    {
        return match (self::normalize($meetingType)) {
            'phone' => 'Phone Call',
            'video' => 'Video Call',
            default => 'In-Person',
        };
    }

    public static function reminderTitle(?string $meetingType): string
    {
        return match (self::normalize($meetingType)) {
            'phone' => 'Phone Appointment Reminder',
            'video' => 'Video Call Appointment Reminder',
            default => 'In-Person Appointment Reminder',
        };
    }

    public static function reminderBody(?string $meetingType): string
    {
        return match (self::normalize($meetingType)) {
            'phone' => 'Please be available to take our call at the scheduled time. Keep your phone on and be in a quiet place so we can speak without interruption.',
            'video' => 'Please join from a quiet location with a stable internet connection a few minutes before your scheduled time. Test your camera and microphone beforehand.',
            default => 'Please aim to arrive at least 10 minutes before your scheduled appointment time to allow time for check-in and ensure your consultation begins promptly.',
        };
    }

    public static function bringTitle(?string $meetingType): string
    {
        return self::normalize($meetingType) === 'in_person'
            ? 'What to Bring'
            : 'What to Have Ready';
    }

    /**
     * @return list<string>
     */
    public static function bringItems(?string $meetingType): array
    {
        return match (self::normalize($meetingType)) {
            'phone' => [
                'Have your phone charged and nearby at the scheduled time.',
                'Valid photo identification details (Passport or Driver\'s License).',
                'All relevant documents related to your visa inquiry within easy reach.',
                'Any previous correspondence from immigration authorities.',
            ],
            'video' => [
                'A working camera, microphone, and stable internet connection.',
                'Valid photo identification (Passport or Driver\'s License).',
                'All relevant documents related to your visa inquiry (digital or printed).',
                'Any previous correspondence from immigration authorities.',
            ],
            default => [
                'Valid photo identification (Passport, Driver\'s License).',
                'All relevant documents related to your visa inquiry.',
                'Any previous correspondence from immigration authorities.',
            ],
        };
    }
}
