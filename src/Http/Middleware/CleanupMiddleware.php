<?php

namespace LucaPon\StatamicContentBackup\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LucaPon\StatamicContentBackup\Http\Services\BackupService;

class CleanupMiddleware
{

    protected $backupService;

    public function __construct(BackupService $backupService)
    {
      $this->backupService = $backupService;
    }

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate()
    {
        $this->backupService->cleanup();
    }

}
