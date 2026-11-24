<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;

use Exception;

class OperationInProgressException extends Exception
{
    public function __construct(string $message = 'Another backup or restore is already running')
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 409);
    }
}
