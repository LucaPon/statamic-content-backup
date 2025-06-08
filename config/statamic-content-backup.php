<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Files or folders you want to include in your backup
    |--------------------------------------------------------------------------
    |
    | Specify the files or folders that you want to include in your backup.
    | Ensure that the paths are correct and accessible.
    |
    */

    'include_files' => [
        '/content',
        '/public/assets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database tables you want to include in your backup
    |--------------------------------------------------------------------------
    |
    | Specify the database tables that you want to include in your backup.
    |
    */

    'include_tables' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Folder
    |--------------------------------------------------------------------------
    |
    | Specify the folder where backups will be stored.
    | Ensure that the folder is writable by the web server.
    |
    */

    'backup_folder' => storage_path('app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Backup Filename Format
    |--------------------------------------------------------------------------
    |
    | Specify the format for the backup filenames.
    | You can use the following placeholders:
    | - {date} - Current date in Ymd format
    | - {time} - Current time in His format
    | - {appName} - Your app name
    |
    */

    'backup_filename_format' => 'backup_{date}_{time}',

];
