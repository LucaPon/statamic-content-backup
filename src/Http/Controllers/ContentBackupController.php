<?php

namespace LucaPon\StatamicContentBackup\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Statamic\Facades\CP\Toast;
use Statamic\Http\Controllers\Controller;
use ZipArchive;
use Illuminate\Support\Facades\File;


class ContentBackupController extends Controller
{

    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index(Request $request)
    {
        return view('statamic-content-backup::index');
    }

    public function downloadBackup(Request $request)
    {
        try {
            $backupFile = $this->backupService->createBackup();
        }catch (\Exception $e) {
            Toast::error('Error creating backup');
            return redirect()->back();
        }

        return response()->download($backupFile)->deleteFileAfterSend(true);
    }


    public function restoreBackup(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'restoreInput' => 'required|file|mimes:zip'
        ]);

        if($validator->fails()) {
            Toast::error('Error restoring backup');
            return redirect()->back();
        }

        try {
            $backupFile = $validator->validated()['restoreInput'];
            $this->backupService->restoreBackup($backupFile);
        }
        catch (\Exception $e) {
            Toast::error('Error restoring backup');
            return redirect()->back();
        }

        Toast::success('Backup restored successfully');
        return redirect()->back();
    }
}
