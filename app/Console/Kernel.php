<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        '\App\Console\Commands\CronJob',
        // '\App\Console\Commands\CompleteTaskRemoval',

        '\App\Console\Commands\InPersonCompleteTaskRemoval',
        '\App\Console\Commands\ProcessServiceAccountTokens',
        '\App\Console\Commands\ClearStuckServiceAccountTokenJobs',
        '\App\Console\Commands\MigrateSecondDatabase',
        '\App\Console\Commands\CleanUtf8Data',
        '\App\Console\Commands\BackfillEoiRoiData',
        '\App\Console\Commands\BackfillEoiAmendmentActions',
        // '\App\Console\Commands\VisaExpireReminderEmail',

        // Appointment Sync System Commands
        '\App\Console\Commands\SyncBansalAppointments',
        '\App\Console\Commands\SendAppointmentReminders',
        '\App\Console\Commands\TestBansalApiConnection',
        '\App\Console\Commands\BackfillBansalAppointments',
        '\App\Console\Commands\RetryInvalidEnquiryTypeSync',
        '\App\Console\Commands\RetryAppointmentNotFoundSync',
        '\App\Console\Commands\MigrateKunalCalendarAppointments',

        // Signature Management System Commands
        '\App\Console\Commands\ArchiveOldDrafts',
        '\App\Console\Commands\SendSignatureReminders',
        '\App\Console\Commands\BackfillVisaSignedDocumentRows',
        '\App\Console\Commands\RetryLocalSignedDocumentsToS3',
        '\App\Console\Commands\RetryLocalSignaturePngsToS3',
        '\App\Console\Commands\MigrateMessageAttachmentsToS3',
        '\App\Console\Commands\RetryLocalAgreementsToS3',

        // SQL Migration Tools
        // '\App\Console\Commands\FixMySqlDumpForPostgres', // Command file does not exist
        // '\App\Console\Commands\FixRemainingSqlIssues', // Command file does not exist

        // Login Data Import
        '\App\Console\Commands\ImportLoginDataFromMySQL',

        // Client Reference Management Commands
        '\App\Console\Commands\FixDuplicateClientReferences',

        // Smart Email Import
        '\App\Console\Commands\CleanSmartEmailImportStaging',

        // Client Age Management Commands
        '\App\Console\Commands\UpdateClientAges',

        // Activity Cleanup Commands
        '\App\Console\Commands\CleanupActivityDescriptions',
        '\App\Console\Commands\BackfillAppointmentActivityLogs',

        // Database Comparison
        '\App\Console\Commands\CompareDatabaseTables',
        '\App\Console\Commands\CheckMigrationTablesExist',
        '\App\Console\Commands\MarkMigrationsAsRunCommand',

        // CRM: align admins.type with active matters
        '\App\Console\Commands\PromoteLeadsWithActiveMatters',

        // Visa Sheet Diagnostics
        '\App\Console\Commands\DiagnoseVisaSheet',
        '\App\Console\Commands\ExpireCrmAccessGrants',
        '\App\Console\Commands\CacheAccessGrantGlobalCounts',
        '\App\Console\Commands\SendFollowUpReminders',
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
        $schedule->command('CronJob:cronjob')->daily();

        // Smart Email Import — prune staging folders older than 24h
        $schedule->command('smart-email-import:clean')
            ->dailyAt('03:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/smart-email-import-clean.log'));
        // $schedule->command('CompleteTaskRemoval:daily')->daily();

        // InPerson Complete Task Removal daily 1 time
        // $schedule->command('InPersonCompleteTaskRemoval:daily')->daily();
        // visa expire Reminder email before 15 days daily at 1 time
        // $schedule->command('VisaExpireReminderEmail:daily')->daily();

        // Appointment Sync System - Sync from Bansal website every 15 minutes (look back 20 minutes for overlap buffer)
        $schedule->command('booking:sync-appointments --minutes=20')
            ->everyFifteenMinutes()
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/appointment-sync.log'));

        // Appointment Sync System - Send reminders daily at 9 AM
        $schedule->command('booking:send-reminders')
            ->dailyAt('09:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/appointment-reminders.log'));

        $schedule->command('followups:send-reminders')
            ->dailyAt('08:30')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/followup-reminders.log'));

        // Signature Management - Archive old drafts daily at 2 AM
        $schedule->command('signatures:archive-drafts --days=30')
            ->daily()
            ->at('02:00')
            ->timezone('Australia/Melbourne')
            ->appendOutputTo(storage_path('logs/signature-archive.log'));

        // Signed documents - retry local signed PDF uploads to S3 daily at 6 PM
        $schedule->command('documents:retry-local-signed-to-s3')
            ->dailyAt('18:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/retry-signed-s3.log'));

        // Signature PNGs - retry failed local uploads to S3 daily at 6:15 PM (new docs that fell back to public/signatures)
        $schedule->command('signatures:retry-local-png-to-s3')
            ->dailyAt('18:15')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/retry-signature-png-s3.log'));

        // Visa agreements - retry local DOCX uploads to S3 daily at 6:30 PM (public/agreements fallback files)
        $schedule->command('agreements:retry-local-to-s3')
            ->dailyAt('18:30')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/retry-agreements-s3.log'));

        // Signature Management - Send auto-reminders daily at 10 AM
        /*$schedule->command('signatures:send-auto-reminders --days=7')
            ->daily()
            ->at('10:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/signature-auto-reminders.log'));*/

        // Client Age Management - Update ages bi-weekly (1st and 15th of each month) at 2 AM
        $schedule->command('clients:update-ages --smart')
            ->twiceMonthly(1, 15)
            ->at('02:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/age-updates.log'));

        $schedule->command('access:expire-grants')->hourly();
        $schedule->command('access:cache-grant-stats')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        // $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
