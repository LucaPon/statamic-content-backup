<?php

namespace LucaPon\StatamicContentBackup\Tests\Feature;

use Illuminate\Support\Facades\File;
use LucaPon\StatamicContentBackup\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\User;

class ManageBackupsTest extends TestCase
{
    public function test_guests_cannot_access_the_backups(): void
    {
        $this->getJson($this->cpRoute('list'))->assertUnauthorized();
        $this->postJson($this->cpRoute('create-backup'))->assertUnauthorized();
    }

    public function test_users_without_permission_cannot_access_the_backups(): void
    {
        $this->actingAs(User::make()->email('editor@example.com')->save());

        $this->getJson($this->cpRoute('list'))->assertForbidden();
        $this->postJson($this->cpRoute('create-backup'))->assertForbidden();
        $this->postJson($this->cpRoute('restore-backup'), ['name' => 'backup.zip'])->assertForbidden();
    }

    public function test_it_lists_the_backups(): void
    {
        $this->makeBackup('first.zip', ['manifest.json' => '{}']);
        $this->makeBackup('second.zip', ['manifest.json' => '{}']);
        File::put($this->backupFolder('notes.txt'), 'not a backup');

        $names = $this->actingAsSuperUser()
            ->getJson($this->cpRoute('list'))
            ->assertOk()
            ->json('*.name');

        sort($names);
        $this->assertSame(['first.zip', 'second.zip'], $names);
    }

    public function test_it_downloads_a_backup(): void
    {
        $this->makeBackup('backup.zip', ['manifest.json' => '{}']);

        $this->actingAsSuperUser()
            ->get($this->cpRoute('download-backup', ['name' => 'backup.zip']))
            ->assertOk()
            ->assertDownload('backup.zip');
    }

    public function test_it_deletes_a_backup(): void
    {
        $this->makeBackup('backup.zip', ['manifest.json' => '{}']);

        $this->actingAsSuperUser()
            ->deleteJson($this->cpRoute('delete-backup'), ['name' => 'backup.zip'])
            ->assertOk();

        $this->assertFileDoesNotExist($this->backupFolder('backup.zip'));
    }

    public function test_missing_backups_are_not_found(): void
    {
        $this->actingAsSuperUser();

        $this->getJson($this->cpRoute('download-backup', ['name' => 'missing.zip']))->assertNotFound();
        $this->postJson($this->cpRoute('restore-backup'), ['name' => 'missing.zip'])->assertNotFound();
    }

    #[DataProvider('invalidBackupNames')]
    public function test_backup_names_outside_the_backup_folder_are_rejected(string $name): void
    {
        $this->actingAsSuperUser();

        $this->getJson($this->cpRoute('download-backup', ['name' => $name]))->assertJsonValidationErrors('name');
        $this->deleteJson($this->cpRoute('delete-backup'), ['name' => $name])->assertJsonValidationErrors('name');
        $this->postJson($this->cpRoute('restore-backup'), ['name' => $name])->assertJsonValidationErrors('name');
    }

    public static function invalidBackupNames(): array
    {
        return [
            'parent folder' => ['../backup.zip'],
            'absolute path' => ['/etc/backup.zip'],
            'not a zip' => ['.env'],
        ];
    }
}
