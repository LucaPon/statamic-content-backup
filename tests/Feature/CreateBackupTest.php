<?php

namespace LucaPon\StatamicContentBackup\Tests\Feature;

use Illuminate\Support\Facades\File;
use LucaPon\StatamicContentBackup\Tests\TestCase;
use ZipArchive;

class CreateBackupTest extends TestCase
{
    public function test_it_backs_up_the_configured_files(): void
    {
        $this->putSiteFile('content/pages/home.md', 'home');
        $this->putSiteFile('public/assets/logo.png', 'logo');
        $this->putSiteFile('config/app.php', 'not included');

        $backupName = $this->actingAsSuperUser()->createBackup();

        $this->assertZipEntries($this->backupFolder($backupName), [
            'files/site/content/pages/home.md',
            'files/site/public/assets/logo.png',
            'manifest.json',
        ]);
    }

    public function test_the_manifest_records_what_each_configured_path_was(): void
    {
        config(['statamic-content-backup.include_files' => ['/site/content', 'site/empty/', '/site/robots.txt', '/site/missing']]);
        $this->putSiteFile('content/home.md');
        $this->putSiteFile('robots.txt');
        File::ensureDirectoryExists($this->sitePath('empty'));

        $backupName = $this->actingAsSuperUser()->createBackup();

        $zip = new ZipArchive();
        $zip->open($this->backupFolder($backupName));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertSame(['files' => [
            '/site/content' => 'directory',
            '/site/empty' => 'directory',
            '/site/robots.txt' => 'file',
            '/site/missing' => 'missing',
        ]], $manifest);
    }

    public function test_the_backup_name_is_safe_to_use_as_a_file_name(): void
    {
        config([
            'app.name' => 'My Site / Staging',
            'statamic-content-backup.backup_filename_format' => '{appName}_{date}',
        ]);

        $backupName = $this->actingAsSuperUser()->createBackup();

        $this->assertMatchesRegularExpression('/^My_Site_Staging_\d{8}\.zip$/', $backupName);
        $this->assertFileExists($this->backupFolder($backupName));
    }

    public function test_an_existing_backup_is_not_overwritten(): void
    {
        config(['statamic-content-backup.backup_filename_format' => 'backup']);
        $this->makeBackup('backup.zip', ['manifest.json' => 'original']);

        $this->actingAsSuperUser()->postJson($this->cpRoute('create-backup'))->assertConflict();

        $this->assertStringContainsString('already exists', $this->getJson($this->cpRoute('status'))->json('error'));
        $zip = new ZipArchive();
        $zip->open($this->backupFolder('backup.zip'));
        $this->assertSame('original', $zip->getFromName('manifest.json'));
    }

    public function test_the_status_is_cleared_once_it_has_been_read(): void
    {
        $this->actingAsSuperUser()->createBackup();

        $this->getJson($this->cpRoute('status'))->assertJson(['runningName' => null, 'success' => null, 'error' => null]);
    }

    public function test_temporary_files_are_removed(): void
    {
        File::ensureDirectoryExists($this->backupFolder('temp'));
        File::put($this->backupFolder('temp/leftover.sql'), 'left by a worker that died');
        $this->putSiteFile('content/home.md');

        $this->actingAsSuperUser()->createBackup();

        $this->assertDirectoryDoesNotExist($this->backupFolder('temp'));
    }
}
