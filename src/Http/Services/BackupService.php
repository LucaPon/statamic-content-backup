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
use LucaPon\StatamicContentBackup\Http\Exceptions\UnsupportedDatabaseDriverException;
use Statamic\Facades\Stache;
use ZipArchive;

class BackupService
{
    private $tempFolderName = "temp";
    private $databaseBasePath = 'database';
    private $filesBasePath = 'files';

    public function listBackups(): array {
        $backupFolder = $this->getBackupFolder();

        $files = File::files($backupFolder);
        $backups = [];

        //order files by creation time, newest first
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
        $appName = env('APP_NAME');
        $format = config('statamic-content-backup.backup_filename_format');
        return str_replace(['{date}', '{time}', '{appName}'], [$date, $time, $appName], $format) . '.zip';
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

            // Move original files to rollback folder
            foreach ($includeFiles as $file) {
                $oldFile = base_path($file);
                if (File::exists($oldFile)) {
                    $rollbackPath = $rollbackFolder . $file;
                    File::ensureDirectoryExists(dirname($rollbackPath));
                    File::move($oldFile, $rollbackPath);
                }
            }

            // Replace files with backup versions
            try {
                foreach ($includeFiles as $file) {
                    $tempFile = $tempFolder . '/' . $this->filesBasePath . '/' . $file;
                    $oldFile = base_path($file);
                    if(File::exists($tempFile)) {
                        File::move($tempFile, $oldFile);
                    }
                }

                DB::beginTransaction();
                try {
                    foreach($includeTables as $table){
                        $tableDumpPath = $tempFolder . '/' . $this->databaseBasePath . '/' . $table . '.sql';
                        if(File::exists($tableDumpPath)) {
                            DB::unprepared(File::get($tableDumpPath));
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } catch (\Exception $e) {
                // Rollback: restore original files
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

            //Refresh Statamic Stache
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
            throw new BackupNotFoundException('Backup file not found: ' . $backupFilePath);
        }

        return $backupFilePath;
    }

    public function checkBackupExists(string $backupName): bool {
        $backupFolder = $this->getBackupFolder();
        $backupFilePath = $backupFolder . '/' . $backupName;

        return File::exists($backupFilePath);
    }

    public function saveUploadedBackup($file): string {
        $backupFolder = $this->getBackupFolder();
        $filePath = $backupFolder . '/' . $file->getClientOriginalName();

        if (!File::move($file->getRealPath(), $filePath)) {
            throw new \Exception('Error saving uploaded backup file');
        }

        return $filePath;
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

        $chunckFolder = storage_path('app/chunks');
        if (File::exists($chunckFolder)) {
            File::deleteDirectory($chunckFolder);
        }

    }
}
