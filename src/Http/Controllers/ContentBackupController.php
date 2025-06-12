<?php

namespace LucaPon\StatamicContentBackup\Http\Controllers;

use LucaPon\StatamicContentBackup\Jobs\BackupJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use LucaPon\StatamicContentBackup\Http\Exceptions\BackupWithSameNameException;
use LucaPon\StatamicContentBackup\Http\Requests\DeleteBackupRequest;
use LucaPon\StatamicContentBackup\Http\Requests\DownloadBackupRequest;
use LucaPon\StatamicContentBackup\Http\Requests\RestoreBackupRequest;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Statamic\Http\Controllers\Controller;

class ContentBackupController extends Controller
{

    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        return view('statamic-content-backup::index');
    }

    public function listBackups()
    {
        $backups = $this->backupService->listBackups();
        return response()->json($backups);
    }

    public function getBackupJobStatus()
    {
        $status = $this->backupService->getBackupJobStatus();

        return response()->json($status);
    }

    public function createBackup(){

        BackupJob::dispatch();

        return response()->json([
            'status' => 'success'
        ]);

    }

    public function deleteBackup(DeleteBackupRequest $request)
    {
        $backupName = $request->input('name');

        try {
            $this->backupService->deleteBackup($backupName);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Failed to delete backup'], 500);
        }

        return response()->json(['success' => 'Backup deleted successfully']);
    }

    public function downloadBackup(DownloadBackupRequest $request)
    {

        $backupName = $request->input('name');

        $backupFile = $this->backupService->getBackupFilePath($backupName) ;

        return response()->download($backupFile);
    }

    public function uploadBackup(FileReceiver $receiver){

        //chunk upload
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $recived = $receiver->receive();

        if ($recived->isFinished()) {

            try {
                $file = $recived->getFile();
                $this->backupService->saveUploadedBackup($file);
            }
            catch (\Exception $e) {
                report($e);
                $this->backupService->cleanUp();
            }

        }

        $handler = $recived->handler();

        if($handler->isFirstChunk()){
            $file = $recived->getFile()->getClientOriginalName();
            if($this->backupService->checkBackupExists($file)){
                //abort the upload and clean up
                $this->backupService->cleanUp();
                return response()->json([
                    "error" => "Backup with the same name already exists."
                ], 400);
            }
        }

        return response()->json([
            "progress" => $handler->getPercentageDone()
        ]);

    }

    public function restoreBackup(RestoreBackupRequest $request)
    {
        $backupName = $request->input('name');

        $this->backupService->restoreBackup($backupName);

        return response()->json(['success' => 'Backup restored successfully']);

    }
}
