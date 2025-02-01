<?php

namespace LucaPon\StatamicContentBackup\Http\Controllers;

use Illuminate\Http\Request;
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

    public function downloadBackup(Request $request)
    {
        try {
            $backupFile = $this->backupService->createBackup();
        }catch (\Exception $e) {
            report($e);
            return redirect()->back();
        }

        return response()->download($backupFile);
    }

    public function restoreBackup(FileReceiver $receiver)
    {

        //chunk upload
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $recived = $receiver->receive();

        if ($recived->isFinished()) {

            try {
                $this->backupService->restoreBackup($recived->getFile());
            }
            catch (\Exception $e) {
                report($e);
                $this->backupService->cleanUp();
            }

        }

        $handler = $recived->handler();
        return response()->json([
            "progress" => $handler->getPercentageDone()
        ]);

    }
}
