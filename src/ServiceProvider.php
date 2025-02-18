<?php

namespace LucaPon\StatamicContentBackup;

use Illuminate\Support\Facades\Route;
use LucaPon\StatamicContentBackup\Http\Controllers\ContentBackupController;
use LucaPon\StatamicContentBackup\Http\Middleware\CleanupMiddleware;
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
                ->icon('download');
        });
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
                    Route::get('/backup', [ContentBackupController::class, 'downloadBackup'])
                        ->middleware(CleanupMiddleware::class)
                        ->name('backup');
                    Route::post('/restore', [ContentBackupController::class, 'restoreBackup'])->name('restore');
                });
        });
    }
}
