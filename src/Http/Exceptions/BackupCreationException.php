<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;

use Exception;

class BackupCreationException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $message
     */
    public function __construct(string $message = 'Error creating backup')
    {
        parent::__construct($message);
    }

    /**
     * Render the exception to the user.
     *
     * @return string
     */
    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 500);
    }
}
