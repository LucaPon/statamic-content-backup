<?php

return [

    /*
    |
    | Files or folders you want to include in your backup
    |
    */
    'include_files' => [
        '/content',
        '/public/assets',
    ],

    /*
    |
    | Files or folders you want to exclude from your backup
    |
    */
    'exclude_files' => [
        'content/taxonomies'
    ],

];
