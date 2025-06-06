<?php

namespace LucaPon\StatamicContentBackup\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;

class BackupJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected BackupService $backupService;

    public function __construct()
    {
        $this->backupService = app(BackupService::class);
    }

    public function handle(): void
    {
        $this->backupService->createBackup();
    }

    public function failed(Exception $exception): void
    {
        Cache::put('statamic-content-backup.backup_job_error', $exception->getMessage());
        $this->backupService->cleanup();
    }


}
