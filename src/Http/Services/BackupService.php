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

    public function listBackups(): array
    {
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
                    'size' => Number::fileSize($file->getSize()),
                    'created' => $file->getCTime(),
                    'modified' => $file->getMTime(),
                ];
            }
        }
        return $backups;
    }

    public function getBackupJobStatus(): array
    {
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

    public function createBackup(): void
    {
        $backupFileName = $this->generateBackupFileName();
        $backupFolder = $this->getBackupFolder();
        $finalBackupPath = $backupFolder . '/' . $backupFileName;

        Cache::put('statamic-content-backup.backup_job_runningName', $backupFileName);

        if( File::exists($finalBackupPath) ) {
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

    private function backupDatabaseTables($zip, $includeTables): void{

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

    private function getDbDumper(): \Spatie\DbDumper\DbDumper
    {

        $databaseConnection = config()->get('database.default');
        $databaseDriver = config()->get('database.connections.' . $databaseConnection . '.driver');

        if($databaseDriver === 'mysql') {

            $dbConfigPrefix = 'database.connections.' . $databaseConnection;
            $dbName = config()->get( $dbConfigPrefix . '.database');
            $dbHost = config()->get( $dbConfigPrefix . '.host');
            $dbPort = config()->get( $dbConfigPrefix . '.port');
            $userName = config()->get( $dbConfigPrefix . '.username');
            $password = config()->get( $dbConfigPrefix . '.password');

            return \Spatie\DbDumper\Databases\MySql::create()
                ->setDbName($dbName)
                ->setHost($dbHost)
                ->setPort($dbPort)
                ->setUserName($userName)
                ->setPassword($password);

        }else {
            throw new UnsupportedDatabaseDriverException($databaseDriver);
        }
    }

    public function deleteBackup($backupName): void
    {
        $backupFolder = $this->getBackupFolder();
        $backupPath = $backupFolder . '/' . $backupName;

        if (!File::exists($backupPath)) {
            throw new BackupDeletionException('Backup file does not exist');
        }
        if (!File::delete($backupPath)) {
            throw new BackupDeletionException('Error deleting backup file');
        }
    }

    public function restoreBackup($backupPath): void
    {
        $includeTables = config()->get('statamic-content-backup.include_tables');
        $includeFiles = config()->get('statamic-content-backup.include_files');

        $zip = new ZipArchive();
        if(!$zip->open($backupPath)){
            throw new \Exception('Error opening backup file');
        }

        try {
            $tempFolder = $this->getTempFolder();
            $zip->extractTo($tempFolder);
            $zip->close();

            foreach ($includeFiles as $file) {
                $tempFile = $tempFolder . '/' . $this->filesBasePath . '/' . $file;
                $oldFile = base_path($file);
                if(File::exists($tempFile)) {

                    if (File::isDirectory($oldFile)) {
                        File::deleteDirectory($oldFile);
                    }
                    if (File::isFile($oldFile)) {
                        File::delete($oldFile);
                    }

                    File::move($tempFile, $oldFile);
                }
            }

            foreach( $includeTables as $table){
                $tableDumpPath = $tempFolder . '/' . $this->databaseBasePath . '/' . $table . '.sql';
                if(File::exists($tableDumpPath)) {
                    DB::unprepared(File::get($tableDumpPath));
                }
            }

            $this->cleanup();

            //Refresh Statamic Stache
            Stache::refresh();

        }catch (\Exception $e) {
            $this->cleanup();
            throw $e;
        }
    }

    public function getBackupFilePath(string $backupName): string
    {
        $backupFolder = $this->getBackupFolder();
        $backupFilePath = $backupFolder . DIRECTORY_SEPARATOR . $backupName;

        if (!File::exists($backupFilePath)) {
            throw new BackupNotFoundException('Backup file not found: ' . $backupFilePath);
        }

        return $backupFilePath;
    }

    private function addToZip($file, $zip, $entryName = null): void
    {
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

    private function getTempFolder(): string
    {
        $tempFolder = config()->get('statamic-content-backup.backup_folder') . DIRECTORY_SEPARATOR . $this->tempFolderName;
        if (!File::exists($tempFolder)) {
            File::makeDirectory($tempFolder, recursive: true);
        }

        return $tempFolder;
    }

    private function getBackupFolder(): string
    {
        $backupFolder = config()->get('statamic-content-backup.backup_folder');
        if (!File::exists($backupFolder)) {
            File::makeDirectory($backupFolder, recursive: true);
        }

        return $backupFolder;
    }

    public function cleanup(): void
    {
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
