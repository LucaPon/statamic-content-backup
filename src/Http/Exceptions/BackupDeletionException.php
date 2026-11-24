<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;

use Exception;

class BackupDeletionException extends Exception
{
    public function __construct(string $message = 'Error deleting backup')
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 500);
    }
}
