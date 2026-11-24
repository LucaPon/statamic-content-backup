<?php

namespace LucaPon\StatamicContentBackup\Http\Controllers;

use LucaPon\StatamicContentBackup\Http\Exceptions\BackupWithSameNameException;
use LucaPon\StatamicContentBackup\Jobs\BackupJob;
use LucaPon\StatamicContentBackup\Jobs\RestoreJob;
use LucaPon\StatamicContentBackup\Http\Requests\DeleteBackupRequest;
use LucaPon\StatamicContentBackup\Http\Requests\DownloadBackupRequest;
use LucaPon\StatamicContentBackup\Http\Requests\RestoreBackupRequest;
use LucaPon\StatamicContentBackup\Http\Requests\UploadBackupRequest;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;
use Statamic\Http\Controllers\Controller;

class ContentBackupController extends Controller
{

    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function listBackups()
    {
        $backups = $this->backupService->listBackups();
        return response()->json($backups);
    }

    public function getBackupJobStatus()
    {
        $status = $this->backupService->getBackupJobStatus();
        $status['restore'] = $this->backupService->getRestoreJobStatus();

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

        $backupFile = $this->backupService->getBackupFilePath($backupName);

        // On some platforms (e.g. Windows) the time limit also counts the time spent sending the file
        @set_time_limit(0);

        return response()->download($backupFile, $backupName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function uploadBackup(UploadBackupRequest $request)
    {
        // Checked on every chunk so a rejected upload stops before the whole file is sent
        $backupName = $this->backupService->normalizeBackupName($request->file('file')->getClientOriginalName());
        if ($this->backupService->checkBackupExists($backupName)) {
            throw new BackupWithSameNameException($backupName);
        }

        $uploadId = $request->input('resumableIdentifier');
        $chunkNumber = (int) $request->input('resumableChunkNumber');
        $totalChunks = (int) $request->input('resumableTotalChunks');

        if ($chunkNumber === 1) {
            $this->backupService->deleteStaleUploads();
        }

        $this->backupService->storeUploadChunk(
            $uploadId,
            $chunkNumber,
            (int) $request->input('resumableChunkSize'),
            $request->file('file')->getRealPath()
        );

        if ($chunkNumber < $totalChunks) {
            return response()->json([
                'progress' => (int) floor($chunkNumber / $totalChunks * 100),
            ]);
        }

        $this->backupService->completeUpload($uploadId, (int) $request->input('resumableTotalSize'), $backupName);

        return response()->json([
            'progress' => 100,
            'name' => $backupName,
        ]);
    }

    public function restoreBackup(RestoreBackupRequest $request)
    {
        $backupName = $request->input('name');

        $this->backupService->getBackupFilePath($backupName);
        $this->backupService->markRestoreStarted($backupName);

        try {
            RestoreJob::dispatch($backupName);
        } catch (\Throwable $e) {
            // With the sync queue the job runs right away: its failure is reported through the job status
            report($e);
        }

        return response()->json(['status' => 'success']);
    }
}
