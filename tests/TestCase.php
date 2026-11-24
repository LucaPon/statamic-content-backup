<?php

namespace LucaPon\StatamicContentBackup\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use LucaPon\StatamicContentBackup\ServiceProvider;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use ZipArchive;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory($this->sitePath());
        File::deleteDirectory($this->backupFolder());
        File::ensureDirectoryExists($this->sitePath());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sitePath());
        File::deleteDirectory($this->backupFolder());

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic-content-backup.include_files', ['/site/content', '/site/public/assets']);
        $app['config']->set('statamic-content-backup.include_tables', []);
        $app['config']->set('statamic-content-backup.backup_folder', storage_path('app/content-backup-tests'));
        $app['config']->set('statamic-content-backup.backup_filename_format', 'backup_{date}_{time}');

        // The database clients need a real file, set DB_CONNECTION to test against another database
        if (env('DB_CONNECTION', 'sqlite') === 'sqlite') {
            $database = storage_path('app/content-backup-tests.sqlite');
            File::put($database, '');

            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite.database', $database);
        }
    }

    protected function sitePath(string $path = ''): string
    {
        return base_path('site' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    protected function backupFolder(string $path = ''): string
    {
        return config('statamic-content-backup.backup_folder') . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    protected function putSiteFile(string $path, string $contents = 'contents'): void
    {
        File::ensureDirectoryExists(dirname($this->sitePath($path)));
        File::put($this->sitePath($path), $contents);
    }

    protected function actingAsSuperUser(): static
    {
        return $this->actingAs(User::make()->email('admin@example.com')->makeSuper()->save());
    }

    protected function cpRoute(string $name, array $parameters = []): string
    {
        return cp_route('utilities.content-backup.' . $name, $parameters);
    }

    protected function createBackup(): string
    {
        $this->postJson($this->cpRoute('create-backup'))->assertOk();

        $status = $this->getJson($this->cpRoute('status'))->json();
        $this->assertNull($status['error']);
        $this->assertNotNull($status['success']);

        return $status['success'];
    }

    /**
     * Returns the status of the restore, which runs in a job.
     */
    protected function restoreBackup(string $backupName): array
    {
        $this->postJson($this->cpRoute('restore-backup'), ['name' => $backupName])->assertOk();

        return $this->getJson($this->cpRoute('status'))->json('restore');
    }

    /**
     * Builds a backup archive by hand, to test archives the current version of the addon would not create.
     */
    protected function makeArchive(string $path, array $entries): string
    {
        File::ensureDirectoryExists(dirname($path));

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            str_ends_with($name, '/') ? $zip->addEmptyDir($name) : $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    protected function makeBackup(string $backupName, array $entries): string
    {
        $this->makeArchive($this->backupFolder($backupName), $entries);

        return $backupName;
    }

    protected function uploadChunk(string $fileName, string $contents, int $chunkNumber, int $chunkSize, string $uploadId = 'upload-1'): TestResponse
    {
        return $this->post($this->cpRoute('upload-backup'), [
            'file' => UploadedFile::fake()->createWithContent($fileName, substr($contents, ($chunkNumber - 1) * $chunkSize, $chunkSize)),
            'resumableIdentifier' => $uploadId,
            'resumableChunkNumber' => $chunkNumber,
            'resumableTotalChunks' => (int) ceil(strlen($contents) / $chunkSize),
            'resumableChunkSize' => $chunkSize,
            'resumableTotalSize' => strlen($contents),
        ], ['Accept' => 'application/json']);
    }

    protected function assertZipEntries(string $path, array $expected): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();

        sort($entries);
        sort($expected);
        $this->assertSame($expected, $entries);
    }
}
