<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;


use Exception;
class BackupWithSameNameException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $backupName
     */
    public function __construct(string $backupName)
    {
        parent::__construct("Backup with the name '{$backupName}' already exists.");
    }

    /**
     * Render the exception to the user.
     *
     * @return string
     */
    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 409);
    }
}
