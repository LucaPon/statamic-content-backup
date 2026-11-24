<?php

namespace LucaPon\StatamicContentBackup\Tests\Feature;

use Illuminate\Support\Facades\File;
use LucaPon\StatamicContentBackup\Tests\TestCase;

class UploadBackupTest extends TestCase
{
    private const CHUNK_SIZE = 100;

    public function test_it_uploads_a_backup_in_chunks(): void
    {
        $contents = $this->backupContents();
        $totalChunks = (int) ceil(strlen($contents) / self::CHUNK_SIZE);
        $this->assertGreaterThan(2, $totalChunks);
        $this->actingAsSuperUser();

        for ($chunk = 1; $chunk < $totalChunks; $chunk++) {
            $this->uploadChunk('backup.zip', $contents, $chunk, self::CHUNK_SIZE)
                ->assertOk()
                ->assertJson(['progress' => (int) floor($chunk / $totalChunks * 100)]);
        }

        $this->uploadChunk('backup.zip', $contents, $totalChunks, self::CHUNK_SIZE)
            ->assertOk()
            ->assertExactJson(['progress' => 100, 'name' => 'backup.zip']);

        $this->assertSame($contents, File::get($this->backupFolder('backup.zip')));
        $this->assertEmpty(File::files($this->backupFolder('uploads')));
    }

    public function test_retried_chunks_do_not_corrupt_the_upload(): void
    {
        $contents = $this->backupContents();
        $totalChunks = (int) ceil(strlen($contents) / self::CHUNK_SIZE);
        $this->actingAsSuperUser();

        foreach ([1, 2, 1, 2, 2, ...range(3, $totalChunks)] as $chunk) {
            $this->uploadChunk('backup.zip', $contents, $chunk, self::CHUNK_SIZE)->assertOk();
        }

        $this->assertSame($contents, File::get($this->backupFolder('backup.zip')));
    }

    public function test_an_upload_with_a_missing_chunk_is_rejected(): void
    {
        $contents = $this->backupContents();
        $this->actingAsSuperUser();

        $this->uploadChunk('backup.zip', $contents, 1, self::CHUNK_SIZE)->assertOk();
        $this->uploadChunk('backup.zip', $contents, 3, self::CHUNK_SIZE)
            ->assertUnprocessable()
            ->assertJson(['error' => 'The upload is incomplete, please try again']);

        $this->assertEmpty(File::files($this->backupFolder('uploads')));
    }

    public function test_only_zip_files_can_be_uploaded(): void
    {
        $this->actingAsSuperUser()
            ->uploadChunk('backup.txt', 'text', 1, self::CHUNK_SIZE)
            ->assertUnprocessable()
            ->assertJson(['error' => 'Only .zip backups can be uploaded']);
    }

    public function test_corrupted_zip_files_are_rejected(): void
    {
        $this->actingAsSuperUser()
            ->uploadChunk('backup.zip', 'not a zip archive', 1, self::CHUNK_SIZE)
            ->assertUnprocessable()
            ->assertJson(['error' => 'The uploaded file is not a valid zip archive']);

        $this->assertFileDoesNotExist($this->backupFolder('backup.zip'));
    }

    public function test_zip_files_that_are_not_backups_are_rejected(): void
    {
        $contents = $this->zipContents(['readme.txt' => 'hello']);

        $this->actingAsSuperUser()
            ->uploadChunk('backup.zip', $contents, 1, strlen($contents))
            ->assertUnprocessable()
            ->assertJson(['error' => 'The uploaded zip is not a content backup']);

        $this->assertFileDoesNotExist($this->backupFolder('backup.zip'));
    }

    public function test_an_existing_backup_is_not_overwritten(): void
    {
        $this->makeBackup('backup.zip', ['manifest.json' => 'original']);
        $contents = $this->backupContents();

        $this->actingAsSuperUser()
            ->uploadChunk('backup.zip', $contents, 1, self::CHUNK_SIZE)
            ->assertConflict();

        $this->assertEmpty(File::glob($this->backupFolder('uploads/*')));
    }

    public function test_the_backup_name_is_safe_to_use_as_a_file_name(): void
    {
        $contents = $this->backupContents();

        $this->actingAsSuperUser()
            ->uploadChunk('My backup (copy).ZIP', $contents, 1, strlen($contents))
            ->assertOk()
            ->assertJson(['name' => 'My_backup_copy.zip']);

        $this->assertFileExists($this->backupFolder('My_backup_copy.zip'));
    }

    public function test_upload_ids_cannot_point_outside_the_uploads_folder(): void
    {
        $this->actingAsSuperUser()
            ->uploadChunk('backup.zip', $this->backupContents(), 1, self::CHUNK_SIZE, '../../evil')
            ->assertJsonValidationErrors('resumableIdentifier');
    }

    public function test_interrupted_uploads_are_deleted_after_a_day(): void
    {
        File::ensureDirectoryExists($this->backupFolder('uploads'));
        File::put($this->backupFolder('uploads/old.part'), 'old');
        File::put($this->backupFolder('uploads/recent.part'), 'recent');
        touch($this->backupFolder('uploads/old.part'), now()->subDays(2)->getTimestamp());

        $this->actingAsSuperUser()
            ->uploadChunk('backup.zip', $this->backupContents(), 1, self::CHUNK_SIZE)
            ->assertOk();

        $this->assertFileDoesNotExist($this->backupFolder('uploads/old.part'));
        $this->assertFileExists($this->backupFolder('uploads/recent.part'));
    }

    public function test_uploaded_backups_can_be_restored(): void
    {
        $contents = $this->backupContents();
        $this->putSiteFile('content/home.md', 'changed');
        $this->actingAsSuperUser()->uploadChunk('backup.zip', $contents, 1, strlen($contents))->assertOk();

        $this->assertSame('backup.zip', $this->restoreBackup('backup.zip')['success']);

        $this->assertSame('home', File::get($this->sitePath('content/home.md')));
    }

    private function backupContents(): string
    {
        return $this->zipContents([
            'manifest.json' => json_encode(['files' => ['/site/content' => 'directory']]),
            'files/site/content/home.md' => 'home',
            'files/site/content/about.md' => str_repeat('about ', 100),
        ]);
    }

    private function zipContents(array $entries): string
    {
        $path = $this->makeArchive(storage_path('app/content-backup-tests-upload.zip'), $entries);
        $contents = File::get($path);
        File::delete($path);

        return $contents;
    }
}
