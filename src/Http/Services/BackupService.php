<?php

namespace LucaPon\StatamicContentBackup\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use LucaPon\StatamicContentBackup\Http\Exceptions\BackupCreationException;
use LucaPon\StatamicContentBackup\Http\Exceptions\BackupDeletionException;
use LucaPon\StatamicContentBackup\Http\Exceptions\BackupNotFoundException;
use LucaPon\StatamicContentBackup\Http\Exceptions\BackupWithSameNameException;
use LucaPon\StatamicContentBackup\Http\Exceptions\InvalidBackupException;
use LucaPon\StatamicContentBackup\Http\Exceptions\OperationInProgressException;
use LucaPon\StatamicContentBackup\Http\Exceptions\UnsupportedDatabaseDriverException;
use Statamic\Facades\Stache;
use ZipArchive;

class BackupService
{
    public const JOB_TIMEOUT = 1200;

    private $tempFolderName = "temp";
    private $uploadsFolderName = "uploads";
    private $databaseBasePath = 'database';
    private $filesBasePath = 'files';
    private $manifestFileName = 'manifest.json';

    public function listBackups(): array {
        $backupFolder = $this->getBackupFolder();

        $files = File::files($backupFolder);
        $backups = [];

        usort($files, function ($a, $b) {
            return $b->getCTime() <=> $a->getCTime();
        });

        foreach ($files as $file) {
            if ($file->getExtension() === 'zip') {
                $backups[] = [
                    'name' => $file->getFilename(),
                    'size' => Number::fileSize($file->getSize(), 0, 3),
                    'created' => $file->getCTime(),
                    'modified' => $file->getMTime(),
                ];
            }
        }
        return $backups;
    }

    public function getBackupJobStatus(): array {
        $status = [
            'runningName' => Cache::get('statamic-content-backup.backup_job_runningName', null),
            'success' => Cache::get('statamic-content-backup.backup_job_success', null),
            'error' => Cache::get('statamic-content-backup.backup_job_error', null),
        ];

        if( $status['success'] || $status['error'] ) {
            Cache::forget('statamic-content-backup.backup_job_runningName');
            Cache::forget('statamic-content-backup.backup_job_success');
            Cache::forget('statamic-content-backup.backup_job_error');
        }

        return $status;
    }

    public function getRestoreJobStatus(): array {
        $status = [
            'runningName' => Cache::get('statamic-content-backup.restore_job_runningName', null),
            'success' => Cache::get('statamic-content-backup.restore_job_success', null),
            'error' => Cache::get('statamic-content-backup.restore_job_error', null),
        ];

        if( $status['success'] || $status['error'] ) {
            Cache::forget('statamic-content-backup.restore_job_runningName');
            Cache::forget('statamic-content-backup.restore_job_success');
            Cache::forget('statamic-content-backup.restore_job_error');
        }

        return $status;
    }

    public function markRestoreStarted(string $backupName): void {
        Cache::forget('statamic-content-backup.restore_job_success');
        Cache::forget('statamic-content-backup.restore_job_error');
        Cache::put('statamic-content-backup.restore_job_runningName', $backupName, self::JOB_TIMEOUT);
    }

    public function markRestoreSucceeded(string $backupName): void {
        Cache::put('statamic-content-backup.restore_job_success', $backupName, now()->addDay());
        Cache::forget('statamic-content-backup.restore_job_runningName');
    }

    public function markRestoreFailed(string $error): void {
        Cache::put('statamic-content-backup.restore_job_error', $error, now()->addDay());
        Cache::forget('statamic-content-backup.restore_job_runningName');
    }

    public function createBackup(): void {
        $this->withOperationLock(function () {
            $backupFileName = $this->generateBackupFileName();
            $backupFolder = $this->getBackupFolder();
            $finalBackupPath = $backupFolder . '/' . $backupFileName;

            Cache::put('statamic-content-backup.backup_job_runningName', $backupFileName, self::JOB_TIMEOUT);

            if( $this->checkBackupExists($backupFileName) ) {
                throw new BackupWithSameNameException($backupFileName);
            }

            $includeTables = config()->get('statamic-content-backup.include_tables');
            $includeFiles = config()->get('statamic-content-backup.include_files');

            $tempFolder = $this->getTempFolder();
            $backupPath = $tempFolder . '/' . $backupFileName;

            $zip = new ZipArchive();
            if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new BackupCreationException('Error creating backup file');
            }

            $this->backupFiles($zip, $includeFiles);
            $this->backupDatabaseTables($zip, $includeTables);
            $zip->addFromString($this->manifestFileName, json_encode($this->buildManifest($includeFiles), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if (!$zip->close()) {
                throw new BackupCreationException('Error closing backup file');
            }

            if (!File::move($backupPath, $finalBackupPath)) {
                throw new BackupCreationException('Error moving backup file to final destination');
            }

            Cache::put('statamic-content-backup.backup_job_success', $backupFileName, now()->addDay());
            Cache::forget('statamic-content-backup.backup_job_runningName');
        });
    }

    private function generateBackupFilename(): string {
        $date = date('Ymd');
        $time = date('His');
        $appName = config('app.name');
        $format = config('statamic-content-backup.backup_filename_format');
        return $this->sanitizeFileName(str_replace(['{date}', '{time}', '{appName}'], [$date, $time, $appName], $format)) . '.zip';
    }

    public function normalizeBackupName(string $fileName): string {
        $fileName = basename($fileName);

        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new InvalidBackupException('Only .zip backups can be uploaded');
        }

        $name = $this->sanitizeFileName(pathinfo($fileName, PATHINFO_FILENAME));

        return ($name !== '' ? $name : 'backup_' . date('Ymd_His')) . '.zip';
    }

    private function sanitizeFileName(string $name): string {
        // Keep in sync with the "name" validation rule of the backup requests
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]+/', '_', $name);

        return trim($name, '_.');
    }

    private function backupFiles($zip, $includeFiles): void {
        foreach ($includeFiles as $file) {
            $file = $this->normalizePath($file);
            $filePath = base_path($file);
            if(File::exists($filePath)) {
                $this->addToZip($filePath, $zip, $this->filesBasePath . $file);
            }else {
                Log::warning("File or directory not found: $filePath");
            }
        }
    }

    private function buildManifest(array $includeFiles): array {
        $files = [];
        foreach ($includeFiles as $file) {
            $filePath = base_path($file);
            $files[$this->normalizePath($file)] = File::isDirectory($filePath) ? 'directory' : (File::isFile($filePath) ? 'file' : 'missing');
        }

        return ['files' => $files];
    }

    /**
     * Configured paths covered by the backup, restored exactly as they were (even when empty or missing).
     * Backups created before the manifest existed only cover the paths they contain.
     */
    private function getRestorableFiles(string $tempFolder, array $includeFiles): array {
        $manifestPath = $tempFolder . '/' . $this->manifestFileName;
        $manifest = File::exists($manifestPath) ? json_decode(File::get($manifestPath), true) : null;

        $files = [];
        foreach ($includeFiles as $file) {
            $file = $this->normalizePath($file);

            if (is_array($manifest)) {
                if (isset($manifest['files'][$file])) {
                    $files[$file] = $manifest['files'][$file];
                }
            } elseif (File::exists($tempFolder . '/' . $this->filesBasePath . $file)) {
                $files[$file] = null;
            }
        }

        return $files;
    }

    private function normalizePath(string $file): string {
        return '/' . trim($file, '/');
    }

    private function backupDatabaseTables($zip, $includeTables): void {

        if(!empty($includeTables)){

            $dbDumper = $this->getDbDumper();

            foreach ($includeTables as $table) {
                $tableDumpPath = $this->getTempFolder() . '/' . $table . '.sql';

                $dbDumper->includeTables([$table])
                    ->dumpToFile($tableDumpPath);

                $this->addToZip($tableDumpPath, $zip, $this->databaseBasePath . '/' . $table . '.sql');
            }
        }
    }

    private function getDbDumper(): \Spatie\DbDumper\DbDumper {

        $databaseConnection = config()->get('database.default');
        $databaseDriver = config()->get('database.connections.' . $databaseConnection . '.driver');
        $dbConfigPrefix = 'database.connections.' . $databaseConnection;

        if ($databaseDriver === 'mysql') {
            return \Spatie\DbDumper\Databases\MySql::create()
                ->setDbName(config()->get($dbConfigPrefix . '.database'))
                ->setHost(config()->get($dbConfigPrefix . '.host'))
                ->setPort(config()->get($dbConfigPrefix . '.port'))
                ->setUserName(config()->get($dbConfigPrefix . '.username'))
                ->setPassword(config()->get($dbConfigPrefix . '.password'));
        }

        if ($databaseDriver === 'pgsql') {
            return \Spatie\DbDumper\Databases\PostgreSql::create()
                ->setDbName(config()->get($dbConfigPrefix . '.database'))
                ->setHost(config()->get($dbConfigPrefix . '.host'))
                ->setPort(config()->get($dbConfigPrefix . '.port'))
                ->setUserName(config()->get($dbConfigPrefix . '.username'))
                ->setPassword(config()->get($dbConfigPrefix . '.password'));
        }

        if ($databaseDriver === 'sqlite') {
            return \Spatie\DbDumper\Databases\Sqlite::create()
                ->setDbName(config()->get($dbConfigPrefix . '.database'));
        }

        throw new UnsupportedDatabaseDriverException($databaseDriver);
    }

    public function deleteBackup($backupName): void {
        $backupFolder = $this->getBackupFolder();
        $backupPath = $backupFolder . '/' . $backupName;

        if (!$this->checkBackupExists($backupName)) {
            throw new BackupDeletionException('Backup file does not exist');
        }
        if (!File::delete($backupPath)) {
            throw new BackupDeletionException('Error deleting backup file');
        }
    }

    public function restoreBackup($backupName): void {
        $this->withOperationLock(fn () => $this->restoreFromBackup($backupName));
    }

    private function restoreFromBackup(string $backupName): void {
        $includeTables = config()->get('statamic-content-backup.include_tables');
        $includeFiles = config()->get('statamic-content-backup.include_files');

        $zip = new ZipArchive();
        $backupPath = $this->getBackupFilePath($backupName);
        if ($zip->open($backupPath) !== true) {
            throw new InvalidBackupException('The backup file is not a valid zip archive');
        }

        $tempFolder = $this->getTempFolder();
        $rollbackFolder = $tempFolder . '/rollback';
        File::ensureDirectoryExists($rollbackFolder);

        $zip->extractTo($tempFolder);
        $zip->close();

        $restorableFiles = $this->getRestorableFiles($tempFolder, $includeFiles);
        $movedFiles = [];
        $replacedFiles = [];

        try {
            foreach (array_keys($restorableFiles) as $file) {
                $oldFile = base_path($file);
                if (File::exists($oldFile)) {
                    $rollbackPath = $rollbackFolder . $file;
                    File::ensureDirectoryExists(dirname($rollbackPath));
                    if (!File::move($oldFile, $rollbackPath)) {
                        throw new \Exception("Error moving $file to the rollback folder");
                    }
                    $movedFiles[] = $file;
                }
            }

            foreach ($restorableFiles as $file => $type) {
                $tempFile = $tempFolder . '/' . $this->filesBasePath . $file;
                $oldFile = base_path($file);
                $replacedFiles[] = $file;

                if (File::exists($tempFile)) {
                    File::ensureDirectoryExists(dirname($oldFile));
                    if (!File::move($tempFile, $oldFile)) {
                        throw new \Exception("Error restoring $file");
                    }
                } elseif ($type === 'directory') {
                    File::ensureDirectoryExists($oldFile);
                }
            }

            foreach($includeTables as $table){
                $tableDumpPath = $tempFolder . '/' . $this->databaseBasePath . '/' . $table . '.sql';
                if(File::exists($tableDumpPath)) {
                    DB::unprepared(File::get($tableDumpPath));
                }
            }
        } catch (\Throwable $e) {
            foreach ($replacedFiles as $file) {
                $oldFile = base_path($file);
                if (File::isDirectory($oldFile)) {
                    File::deleteDirectory($oldFile);
                }
                if (File::isFile($oldFile)) {
                    File::delete($oldFile);
                }
            }
            foreach ($movedFiles as $file) {
                File::move($rollbackFolder . $file, base_path($file));
            }
            throw $e;
        }

        Stache::refresh();
    }

    public function getBackupFilePath(string $backupName): string {
        $backupFolder = $this->getBackupFolder();
        $backupFilePath = $backupFolder . '/' . $backupName;

        if (!$this->checkBackupExists($backupName)) {
            throw new BackupNotFoundException('Backup file not found: ' . $backupName);
        }

        return $backupFilePath;
    }

    public function checkBackupExists(string $backupName): bool {
        $backupFolder = $this->getBackupFolder();
        $backupFilePath = $backupFolder . '/' . $backupName;

        return File::exists($backupFilePath);
    }

    /**
     * Writes the chunk at its offset, so a retried chunk overwrites itself and no final merge is needed.
     */
    public function storeUploadChunk(string $uploadId, int $chunkNumber, int $chunkSize, string $chunkPath): void {
        $partialPath = $this->getPartialUploadPath($uploadId);
        $offset = ($chunkNumber - 1) * $chunkSize;

        clearstatcache(true, $partialPath);
        $currentSize = File::exists($partialPath) ? File::size($partialPath) : 0;

        if ($currentSize < $offset) {
            File::delete($partialPath);
            throw new InvalidBackupException('The upload is incomplete, please try again');
        }

        $source = fopen($chunkPath, 'rb');
        $target = fopen($partialPath, 'cb');

        try {
            if (fseek($target, $offset) !== 0 || stream_copy_to_stream($source, $target) === false) {
                throw new \Exception('Error writing uploaded chunk');
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    public function completeUpload(string $uploadId, int $totalSize, string $backupName): void {
        $partialPath = $this->getPartialUploadPath($uploadId);

        try {
            clearstatcache(true, $partialPath);
            if (!File::exists($partialPath) || File::size($partialPath) !== $totalSize) {
                throw new InvalidBackupException('The upload is incomplete, please try again');
            }

            $this->saveUploadedBackup($partialPath, $backupName);
        } finally {
            if (File::exists($partialPath)) {
                File::delete($partialPath);
            }
        }
    }

    public function deleteStaleUploads(): void {
        $threshold = now()->subDay()->getTimestamp();

        foreach (File::files($this->getUploadsFolder()) as $file) {
            if ($file->getMTime() < $threshold) {
                File::delete($file->getPathname());
            }
        }
    }

    private function getPartialUploadPath(string $uploadId): string {
        return $this->getUploadsFolder() . '/' . $uploadId . '.part';
    }

    private function getUploadsFolder(): string {
        $uploadsFolder = $this->getBackupFolder() . '/' . $this->uploadsFolderName;
        File::ensureDirectoryExists($uploadsFolder);

        return $uploadsFolder;
    }

    public function saveUploadedBackup(string $sourcePath, string $backupName): string {
        $this->assertValidBackupArchive($sourcePath);

        if ($this->checkBackupExists($backupName)) {
            throw new BackupWithSameNameException($backupName);
        }

        $filePath = $this->getBackupFolder() . '/' . $backupName;

        if (!File::move($sourcePath, $filePath)) {
            throw new \Exception('Error saving uploaded backup file');
        }

        return $filePath;
    }

    private function assertValidBackupArchive(string $path): void {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new InvalidBackupException('The uploaded file is not a valid zip archive');
        }

        // Only archives created by this addon contain these entries
        $isBackup = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === $this->manifestFileName || str_starts_with($entry, $this->filesBasePath . '/') || str_starts_with($entry, $this->databaseBasePath . '/')) {
                $isBackup = true;
                break;
            }
        }
        $zip->close();

        if (!$isBackup) {
            throw new InvalidBackupException('The uploaded zip is not a content backup');
        }
    }

    private function addToZip($file, $zip, $entryName = null): void {
        if (!file_exists($file)) {
            return;
        }

        $entryName = $entryName ?? str_replace(base_path(), '', $file);

        if (is_file($file)) {
            $zip->addFile($file, $entryName);
        } elseif (is_dir($file)) {
            $files = File::allFiles($file, true);
            foreach ($files as $file) {
                $this->addToZip($file, $zip, $entryName . '/' . $file->getRelativePathname());
            }
        }
    }

    private function getTempFolder(): string {
        $tempFolder = config()->get('statamic-content-backup.backup_folder') . '/' . $this->tempFolderName;
        if (!File::exists($tempFolder)) {
            File::makeDirectory($tempFolder, recursive: true);
        }

        return $tempFolder;
    }

    private function getBackupFolder(): string {
        $backupFolder = config()->get('statamic-content-backup.backup_folder');
        if (!File::exists($backupFolder)) {
            File::makeDirectory($backupFolder, recursive: true);
        }

        return $backupFolder;
    }

    /**
     * Backups and restores share the temp folder, so only one of them can run at a time.
     */
    private function withOperationLock(callable $callback): void {
        $lock = Cache::lock('statamic-content-backup.operation', self::JOB_TIMEOUT);

        if (!$lock->get()) {
            throw new OperationInProgressException();
        }

        try {
            // Leftovers of an operation whose worker died
            $this->cleanup();

            $callback();
        } finally {
            $this->cleanup();
            $lock->release();
        }
    }

    private function cleanup(): void {
        $tempFolder = $this->getTempFolder();
        if (File::exists($tempFolder)) {
            File::deleteDirectory($tempFolder);
        }
    }
}
