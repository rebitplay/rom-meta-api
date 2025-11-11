<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Libretro Database Path
    |--------------------------------------------------------------------------
    |
    | The absolute path to the libretro-database directory containing
    | the DAT files and metadata.
    |
    */
    'libretro_db_path' => env('LIBRETRO_DB_PATH', base_path('../libretro-database')),

    /*
    |--------------------------------------------------------------------------
    | Import Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of games to import in each batch to prevent memory issues
    | with large DAT files.
    |
    */
    'import_batch_size' => env('ROM_IMPORT_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Source Precedence
    |--------------------------------------------------------------------------
    |
    | The order of precedence for importing game data from different sources.
    | Earlier sources in the list will override later sources.
    |
    */
    'source_precedence' => [
        'dat',
        'no-intro',
        'redump',
        'tosec',
    ],
];
