<?php

namespace LucaPon\StatamicContentBackup;

use Illuminate\Support\Facades\Route;
use LucaPon\StatamicContentBackup\Http\Controllers\ContentBackupController;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
        parent::bootAddon();

        $this->setConfig();
        $this->setUtility();
    }

    private function setUtility(): void
    {
        Utility::extend(function ($utilities) {
            $utilities->register('content_backup')
                ->title('Content Backup')
                ->description('Create, upload, download and restore content backups')
                ->icon('package-box-crate')
                ->inertia('statamic-content-backup::ContentBackupUtility', function () {
                    return [
                        'token' => csrf_token(),
                        'listUrl' => cp_route('utilities.content-backup.list'),
                        'statusUrl' => cp_route('utilities.content-backup.status'),
                        'createUrl' => cp_route('utilities.content-backup.create-backup'),
                        'deleteUrl' => cp_route('utilities.content-backup.delete-backup'),
                        'downloadUrl' => cp_route('utilities.content-backup.download-backup'),
                        'uploadUrl' => cp_route('utilities.content-backup.upload-backup'),
                        'restoreUrl' => cp_route('utilities.content-backup.restore-backup'),
                    ];
                })
                ->routes(function () {
                    Route::get('backups', [ContentBackupController::class, 'listBackups'])->name('list');
                    Route::get('status', [ContentBackupController::class, 'getBackupJobStatus'])->name('status');
                    Route::post('backup', [ContentBackupController::class, 'createBackup'])->name('create-backup');
                    Route::delete('delete', [ContentBackupController::class, 'deleteBackup'])->name('delete-backup');
                    Route::get('download', [ContentBackupController::class, 'downloadBackup'])->name('download-backup');
                    Route::post('upload', [ContentBackupController::class, 'uploadBackup'])->name('upload-backup');
                    Route::post('restore', [ContentBackupController::class, 'restoreBackup'])->name('restore-backup');
                });
        });
    }

    private function setConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/statamic-content-backup.php' => config_path('statamic-content-backup.php')
        ], 'statamic-content-backup');
    }
}
