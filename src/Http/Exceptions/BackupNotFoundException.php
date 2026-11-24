<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;

use Exception;

class BackupNotFoundException extends Exception
{
    public function __construct(string $message = 'Backup not found')
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 500);
    }
}
