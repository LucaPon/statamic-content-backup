<?php

namespace LucaPon\StatamicContentBackup;

use Illuminate\Support\Facades\Route;
use LucaPon\StatamicContentBackup\Http\Controllers\ContentBackupController;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        $this->setNav();
        $this->setConfig();
        $this->setPermissions();
        $this->setCpRoutes();
    }

    private function setNav()
    {
        Nav::extend(function ($nav) {
            $nav->content('Backup')
                ->section('Tools')
                ->can('statamic-content-backup-permission')
                ->route('statamic-content-backup.index')
                ->icon('download');
        });
    }

    private function setConfig()
    {
        $this->publishes([
            __DIR__.'/../config/statamic-content-backup.php' => config_path('statamic/statamic-content-backup.php')
        ], 'config');
    }

    private function setPermissions()
    {
        Permission::group('statamic-content-backup', 'Backup', function () {
            Permission::register('statamic-content-backup-permission')
                ->label('Download and restore content backups');
        });
    }

    private function setCpRoutes()
    {
        $this->registerCpRoutes(function () {
            Route::name('statamic-content-backup.')
                ->prefix('/statamic-content-backup')
                ->middleware('can:statamic-content-backup-permission')
                ->group(function () {
                    Route::get('/', [ContentBackupController::class, 'index'])->name('index');
                    Route::get('/backup', [ContentBackupController::class, 'downloadBackup'])->name('backup');
                    Route::post('/restore', [ContentBackupController::class, 'restoreBackup'])->name('restore');
                });
        });
    }
}
