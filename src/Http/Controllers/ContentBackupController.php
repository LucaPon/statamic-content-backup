<?php

namespace LucaPon\StatamicContentBackup\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\CP\Toast;
use Statamic\Http\Controllers\Controller;
use ZipArchive;
use Illuminate\Support\Facades\File;


class ContentBackupController extends Controller
{
    public function index(Request $request)
    {
        return view('statamic-content-backup::index');
    }

    public function downloadBackup(Request $request)
    {
        $includeFiles = config()->get('statamic-content-backup.include_files');
        $zipFileName = date('Ymd') . '_' . env('APP_NAME') . '.zip';
        $zip = new ZipArchive();
        $zip->open(storage_path($zipFileName), ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($includeFiles as $file) {
            $this->addToZip(base_path($file), $zip);
        }

        if(!$zip->close()){
            Toast::error('Error creating backup');
            return redirect()->back();
        }

        return response()->download(storage_path($zipFileName))->deleteFileAfterSend(true);
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

    public function restoreBackup(Request $request)
    {
        $validated = $request->validate([
            'backup' => 'required|file|mimes:zip'
        ]);
        
        $includeFiles = config()->get('statamic-content-backup.include_files');

        Toast::success('Backup restored successfully');
        return redirect()->back();
    }
}
