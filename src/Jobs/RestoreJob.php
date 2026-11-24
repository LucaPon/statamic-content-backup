<?php

namespace LucaPon\StatamicContentBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;
use Throwable;

class RestoreJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    protected BackupService $backupService;

    public function __construct(protected string $backupName)
    {
        $this->backupService = app(BackupService::class);
    }

    public function handle(): void
    {
        $this->backupService->restoreBackup($this->backupName);
        $this->backupService->markRestoreSucceeded($this->backupName);
    }

    public function failed(Throwable $exception): void
    {
        $this->backupService->markRestoreFailed($exception->getMessage());
        $this->backupService->cleanup();
    }
}
