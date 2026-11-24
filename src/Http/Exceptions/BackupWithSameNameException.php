<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;


use Exception;
class BackupWithSameNameException extends Exception
{
    public function __construct(string $backupName)
    {
        parent::__construct("Backup with the name '{$backupName}' already exists.");
    }

    public function render()
    {
        return response()->json(['error' => $this->getMessage()], 409);
    }
}
