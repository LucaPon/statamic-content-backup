<?php

namespace LucaPon\StatamicContentBackup\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Http\Controllers\Controller;

class ContentBackupController extends Controller
{
    public function index(Request $request)
    {
        return view('statamic-content-backup::index');
    }

    public function downloadBackup(Request $request)
    {
    }

    public function restoreBackup(Request $request)
    {

    }
}
