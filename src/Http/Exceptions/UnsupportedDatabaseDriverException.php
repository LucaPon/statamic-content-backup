<?php

namespace LucaPon\StatamicContentBackup\Http\Exceptions;

use Exception;

class UnsupportedDatabaseDriverException extends Exception
{
    public function __construct(string $driver)
    {
        parent::__construct("The database driver '{$driver}' is not supported for content backup.");
    }
}

