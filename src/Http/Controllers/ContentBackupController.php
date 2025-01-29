<?php

namespace LucaPon\StatamicContentBackup\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;
use Statamic\Facades\CP\Toast;
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
            Toast::error('Error creating backup');
            return redirect()->back();
        }

        return response()->download($backupFile);
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
            report($e);
            $this->backupService->cleanUp();
            Toast::error('Error restoring backup');
            return redirect()->back();
        }

        Toast::success('Backup restored successfully');
        return redirect()->back();
    }
}
