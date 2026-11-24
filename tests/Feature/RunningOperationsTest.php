<?php

namespace LucaPon\StatamicContentBackup\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;
use LucaPon\StatamicContentBackup\Tests\TestCase;

class RunningOperationsTest extends TestCase
{
    public function test_a_backup_cannot_start_while_another_operation_is_running(): void
    {
        Cache::lock('statamic-content-backup.operation', BackupService::JOB_TIMEOUT)->get();

        $this->actingAsSuperUser()->postJson($this->cpRoute('create-backup'))->assertConflict();

        $this->assertSame('Another backup or restore is already running', $this->getJson($this->cpRoute('status'))->json('error'));
        $this->assertEmpty(File::glob($this->backupFolder('*.zip')));
    }

    public function test_a_restore_cannot_start_while_another_operation_is_running(): void
    {
        $this->putSiteFile('content/home.md', 'home');
        $backupName = $this->actingAsSuperUser()->createBackup();
        $this->putSiteFile('content/home.md', 'changed');
        File::ensureDirectoryExists($this->backupFolder('temp'));
        File::put($this->backupFolder('temp/in-progress.sql'), 'used by the running operation');

        Cache::lock('statamic-content-backup.operation', BackupService::JOB_TIMEOUT)->get();

        $this->assertSame('Another backup or restore is already running', $this->restoreBackup($backupName)['error']);
        $this->assertSame('changed', File::get($this->sitePath('content/home.md')));
        $this->assertFileExists($this->backupFolder('temp/in-progress.sql'));
    }

    public function test_the_lock_of_a_worker_that_died_expires(): void
    {
        Cache::lock('statamic-content-backup.operation', BackupService::JOB_TIMEOUT)->get();

        $this->travel(BackupService::JOB_TIMEOUT + 1)->seconds();

        $this->actingAsSuperUser()->createBackup();
    }

    public function test_the_running_status_of_a_worker_that_died_expires(): void
    {
        Cache::put('statamic-content-backup.backup_job_runningName', 'backup.zip', BackupService::JOB_TIMEOUT);
        app(BackupService::class)->markRestoreStarted('backup.zip');
        $this->actingAsSuperUser();

        $this->getJson($this->cpRoute('status'))->assertJson(['runningName' => 'backup.zip', 'restore' => ['runningName' => 'backup.zip']]);

        $this->travel(BackupService::JOB_TIMEOUT + 1)->seconds();

        $this->getJson($this->cpRoute('status'))->assertJson(['runningName' => null, 'restore' => ['runningName' => null]]);
    }
}
