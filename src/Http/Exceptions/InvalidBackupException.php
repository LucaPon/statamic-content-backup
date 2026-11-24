<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;

use Exception;

class InvalidBackupException extends Exception
{
    public function __construct(string $message = 'Invalid backup file')
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 422);
    }
}
