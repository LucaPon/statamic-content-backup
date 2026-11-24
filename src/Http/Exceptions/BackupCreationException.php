<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;

use Exception;

class BackupCreationException extends Exception
{
    public function __construct(string $message = 'Error creating backup')
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 500);
    }
}
