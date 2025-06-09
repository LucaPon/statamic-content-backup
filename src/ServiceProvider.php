<?php

namespace LucaPon\StatamicContentBackup;

use Illuminate\Support\Facades\Route;
use LucaPon\StatamicContentBackup\Http\Controllers\ContentBackupController;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
        parent::bootAddon();

        $this->setNav();
        $this->setConfig();
        $this->setPermissions();
        $this->setCpRoutes();
    }

    private function setNav(): void
    {
        Nav::extend(function ($nav) {
            $nav->content('Backup')
                ->section('Tools')
                ->can('statamic-content-backup-permission')
                ->route('statamic-content-backup.index')
                ->icon(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="feather feather-hard-drive"><line x1="22" y1="12" x2="2" y2="12"></line><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path><line x1="6" y1="16" x2="6.01" y2="16"></line><line x1="10" y1="16" x2="10.01" y2="16"></line></svg>'
                );
        });
    }

    private function setConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/statamic-content-backup.php' => config_path('statamic-content-backup.php')
        ], 'statamic-content-backup');
    }

    private function setPermissions(): void
    {
        Permission::group('statamic-content-backup', 'Backup', function () {
            Permission::register('statamic-content-backup-permission')
                ->label('Download and restore content backups');
        });
    }

    private function setCpRoutes(): void
    {
        $this->registerCpRoutes(function () {
            Route::name('statamic-content-backup.')
                ->prefix('/statamic-content-backup')
                ->middleware('can:statamic-content-backup-permission')
                ->group(function () {
                    Route::get('/', [ContentBackupController::class, 'index'])->name('index');
                    Route::get('/backups', [ContentBackupController::class, 'listBackups'])->name('list');
                    Route::get('/status', [ContentBackupController::class, 'getBackupJobStatus'])->name('status');
                    Route::post('/backup', [ContentBackupController::class, 'createBackup'])->name('createBackup');
                    Route::delete('/delete', [ContentBackupController::class, 'deleteBackup'])->name('deleteBackup');
                    Route::get('/download', [ContentBackupController::class, 'downloadBackup'])->name('downloadBackup');
                    Route::post('/upload', [ContentBackupController::class, 'uploadBackup'])->name('uploadBackup');
                    Route::post('/restore', [ContentBackupController::class, 'restoreBackup'])->name('restoreBackup');
                });
        });
    }
}
