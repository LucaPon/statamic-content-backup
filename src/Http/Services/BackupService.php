<?php

namespace LucaPon\StatamicContentBackup\Http\Services;

use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupService
{
    private $tempFolderName = "temp";
    private $config = "statamic-content-backup.include_files";

    public function createBackup(): string
    {
        $includeFiles = config()->get($this->config);
        $backupFileName = date('Ymd') . '_' . env('APP_NAME') . '.zip';

        $zip = new ZipArchive();
        $backupPath = storage_path() . DIRECTORY_SEPARATOR . $backupFileName;
        $zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($includeFiles as $file) {
            $fileName = base_path($file);
            if(File::exists($fileName)) {
                $this->addToZip($fileName, $zip);
            }
        }

        if(!$zip->close()){
            throw new \Exception('Error creating backup');
        }

        return $backupPath;

    }

    public function restoreBackup($backupPath): void
    {

        $includeFiles = config()->get($this->config);

        $zip = new ZipArchive();
        if(!$zip->open($backupPath)){
            throw new \Exception('Error opening backup file');
        }

        try {
            $tempFolder = $this->getTempFolder();
            $zip->extractTo($tempFolder);
            $zip->close();

            foreach ($includeFiles as $file) {
                $tempFile = $tempFolder . DIRECTORY_SEPARATOR . $file;
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

            $this->cleanTempFolder();

        }catch (\Exception $e) {
            $this->cleanTempFolder();
            throw $e;
        }
    }

    private function addToZip($file, $zip): void
    {
        if (!file_exists($file)) {
            return;
        }

        if (is_file($file)) {
            $zip->addFile($file, str_replace(base_path(), '', $file));
        } elseif (is_dir($file)) {
            $files = File::allFiles($file, true);
            foreach ($files as $file) {
                $this->addToZip($file, $zip);
            }
        }
    }

    private function getTempFolder(): string
    {
        $tempFolder = storage_path($this->tempFolderName);
        if (!File::exists($tempFolder)) {
            File::makeDirectory($tempFolder);
        }

        return $tempFolder;
    }

    private function cleanTempFolder(): void
    {
        $tempFolder = storage_path($this->tempFolderName);
        if (File::exists($tempFolder)) {
            File::deleteDirectory($tempFolder);
        }

    }
}
