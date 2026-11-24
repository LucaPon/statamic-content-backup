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
use LucaPon\StatamicContentBackup\Http\Exceptions\UnsupportedDatabaseDriverException;
use Statamic\Facades\Stache;
use ZipArchive;

class BackupService
{
    private $tempFolderName = "temp";
    private $uploadsFolderName = "uploads";
    private $databaseBasePath = 'database';
    private $filesBasePath = 'files';

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

    public function createBackup(): void {
        $backupFileName = $this->generateBackupFileName();
        $backupFolder = $this->getBackupFolder();
        $finalBackupPath = $backupFolder . '/' . $backupFileName;

        Cache::put('statamic-content-backup.backup_job_runningName', $backupFileName);

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

        if (!$zip->close()) {
            throw new BackupCreationException('Error closing backup file');
        }

        if (!File::move($backupPath, $finalBackupPath)) {
            throw new BackupCreationException('Error moving backup file to final destination');
        }

        $this->cleanup();


        Cache::put('statamic-content-backup.backup_job_success', $backupFileName);
        Cache::forget('statamic-content-backup.backup_job_runningName');

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
            $filePath = base_path($file);
            if(File::exists($filePath)) {
                $this->addToZip($filePath, $zip, $this->filesBasePath . $file);
            }else {
                Log::warning("File or directory not found: $filePath");
            }
        }
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
        $includeTables = config()->get('statamic-content-backup.include_tables');
        $includeFiles = config()->get('statamic-content-backup.include_files');

        $zip = new ZipArchive();
        $backupPath = $this->getBackupFilePath($backupName);
        if(!$zip->open($backupPath)){
            throw new \Exception('Error opening backup file');
        }

        try {
            $tempFolder = $this->getTempFolder();
            $rollbackFolder = $tempFolder . '/rollback';
            File::ensureDirectoryExists($rollbackFolder);

            $zip->extractTo($tempFolder);
            $zip->close();

            foreach ($includeFiles as $file) {
                $oldFile = base_path($file);
                if (File::exists($oldFile)) {
                    $rollbackPath = $rollbackFolder . $file;
                    File::ensureDirectoryExists(dirname($rollbackPath));
                    File::move($oldFile, $rollbackPath);
                }
            }

            try {
                foreach ($includeFiles as $file) {
                    $tempFile = $tempFolder . '/' . $this->filesBasePath . '/' . $file;
                    $oldFile = base_path($file);
                    if(File::exists($tempFile)) {
                        File::move($tempFile, $oldFile);
                    }
                }

                foreach($includeTables as $table){
                    $tableDumpPath = $tempFolder . '/' . $this->databaseBasePath . '/' . $table . '.sql';
                    if(File::exists($tableDumpPath)) {
                        DB::unprepared(File::get($tableDumpPath));
                    }
                }
            } catch (\Exception $e) {
                foreach ($includeFiles as $file) {
                    $rollbackPath = $rollbackFolder . $file;
                    $oldFile = base_path($file);
                    if (File::exists($rollbackPath)) {
                        if (File::isDirectory($oldFile)) {
                            File::deleteDirectory($oldFile);
                        }
                        if (File::isFile($oldFile)) {
                            File::delete($oldFile);
                        }
                        File::move($rollbackPath, $oldFile);
                    }
                }
                $this->cleanup();
                throw $e;
            }

            $this->cleanup();

            Stache::refresh();

        }catch (\Exception $e) {
            $this->cleanup();
            throw $e;
        }
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
            if (str_starts_with($entry, $this->filesBasePath . '/') || str_starts_with($entry, $this->databaseBasePath . '/')) {
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

    public function cleanup(): void {
        $tempFolder = $this->getTempFolder();
        if (File::exists($tempFolder)) {
            File::deleteDirectory($tempFolder);
        }
    }
}
