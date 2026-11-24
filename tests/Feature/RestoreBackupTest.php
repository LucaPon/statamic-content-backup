<?php

namespace LucaPon\StatamicContentBackup\Tests\Feature;

use Illuminate\Support\Facades\File;
use LucaPon\StatamicContentBackup\Tests\TestCase;

class RestoreBackupTest extends TestCase
{
    public function test_files_are_restored_exactly_as_they_were(): void
    {
        $this->putSiteFile('content/home.md', 'home');
        $this->putSiteFile('content/about.md', 'about');
        $this->putSiteFile('public/assets/logo.png', 'logo');
        $backupName = $this->actingAsSuperUser()->createBackup();

        $this->putSiteFile('content/home.md', 'changed');
        File::delete($this->sitePath('content/about.md'));
        $this->putSiteFile('content/new.md', 'new');
        File::deleteDirectory($this->sitePath('public/assets'));

        $this->assertSame(['runningName' => null, 'success' => $backupName, 'error' => null], $this->restoreBackup($backupName));

        $this->assertSame('home', File::get($this->sitePath('content/home.md')));
        $this->assertSame('about', File::get($this->sitePath('content/about.md')));
        $this->assertFileDoesNotExist($this->sitePath('content/new.md'));
        $this->assertSame('logo', File::get($this->sitePath('public/assets/logo.png')));
        $this->assertDirectoryDoesNotExist($this->backupFolder('temp'));
    }

    public function test_empty_and_missing_paths_are_restored_too(): void
    {
        config(['statamic-content-backup.include_files' => ['/site/empty', '/site/missing']]);
        File::ensureDirectoryExists($this->sitePath('empty'));
        $backupName = $this->actingAsSuperUser()->createBackup();

        $this->putSiteFile('empty/new.md');
        $this->putSiteFile('missing/new.md');

        $this->assertSame($backupName, $this->restoreBackup($backupName)['success']);

        $this->assertDirectoryExists($this->sitePath('empty'));
        $this->assertEmpty(File::allFiles($this->sitePath('empty')));
        $this->assertFileDoesNotExist($this->sitePath('missing'));
    }

    public function test_paths_added_to_the_config_after_the_backup_are_left_untouched(): void
    {
        config(['statamic-content-backup.include_files' => ['/site/content']]);
        $this->putSiteFile('content/home.md', 'home');
        $backupName = $this->actingAsSuperUser()->createBackup();

        config(['statamic-content-backup.include_files' => ['/site/content', '/site/public/assets']]);
        $this->putSiteFile('public/assets/logo.png', 'logo');

        $this->assertSame($backupName, $this->restoreBackup($backupName)['success']);

        $this->assertSame('logo', File::get($this->sitePath('public/assets/logo.png')));
    }

    public function test_paths_removed_from_the_config_are_left_untouched(): void
    {
        $this->putSiteFile('content/home.md', 'home');
        $this->putSiteFile('public/assets/logo.png', 'logo');
        $backupName = $this->actingAsSuperUser()->createBackup();

        config(['statamic-content-backup.include_files' => ['/site/content']]);
        $this->putSiteFile('public/assets/logo.png', 'changed');

        $this->assertSame($backupName, $this->restoreBackup($backupName)['success']);

        $this->assertSame('changed', File::get($this->sitePath('public/assets/logo.png')));
    }

    public function test_backups_without_a_manifest_only_restore_the_paths_they_contain(): void
    {
        $backupName = $this->makeBackup('legacy.zip', ['files/site/content/home.md' => 'home']);
        $this->putSiteFile('content/new.md', 'new');
        $this->putSiteFile('public/assets/logo.png', 'logo');

        $this->assertSame($backupName, $this->actingAsSuperUser()->restoreBackup($backupName)['success']);

        $this->assertSame('home', File::get($this->sitePath('content/home.md')));
        $this->assertFileDoesNotExist($this->sitePath('content/new.md'));
        $this->assertSame('logo', File::get($this->sitePath('public/assets/logo.png')));
    }

    public function test_files_are_rolled_back_when_the_restore_fails(): void
    {
        config(['statamic-content-backup.include_tables' => ['broken']]);
        $backupName = $this->makeBackup('broken.zip', [
            'manifest.json' => json_encode(['files' => ['/site/content' => 'directory', '/site/public/assets' => 'missing']]),
            'files/site/content/from-backup.md' => 'from backup',
            'database/broken.sql' => 'THIS IS NOT SQL;',
        ]);
        $this->putSiteFile('content/home.md', 'home');
        $this->putSiteFile('public/assets/logo.png', 'logo');

        $status = $this->actingAsSuperUser()->restoreBackup($backupName);

        $this->assertNull($status['success']);
        $this->assertStringContainsString('Error importing broken.sql', $status['error']);
        $this->assertSame('home', File::get($this->sitePath('content/home.md')));
        $this->assertFileDoesNotExist($this->sitePath('content/from-backup.md'));
        $this->assertSame('logo', File::get($this->sitePath('public/assets/logo.png')));
        $this->assertDirectoryDoesNotExist($this->backupFolder('temp'));
    }

    public function test_corrupted_archives_are_not_restored(): void
    {
        File::ensureDirectoryExists($this->backupFolder());
        File::put($this->backupFolder('corrupted.zip'), 'not a zip archive');
        $this->putSiteFile('content/home.md', 'home');

        $status = $this->actingAsSuperUser()->restoreBackup('corrupted.zip');

        $this->assertSame('The backup file is not a valid zip archive', $status['error']);
        $this->assertSame('home', File::get($this->sitePath('content/home.md')));
    }

    public function test_the_restore_status_is_cleared_once_it_has_been_read(): void
    {
        $backupName = $this->actingAsSuperUser()->createBackup();
        $this->restoreBackup($backupName);

        $this->getJson($this->cpRoute('status'))->assertJson(['restore' => ['runningName' => null, 'success' => null, 'error' => null]]);
    }
}
