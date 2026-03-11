<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default disk to use for storing files. This should be one of the
    | disks configured in config/filesystems.php.
    |
    */
    'default_disk' => env('FILEXUS_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The primary key type for the files table. Supported values:
    |   - 'id' (default): Auto-incrementing integer
    |   - 'uuid': UUID v4 (uses Laravel's HasUuids trait)
    |   - 'ulid': ULID (uses Laravel's HasUlids trait)
    |
    | Note: This must be set before running migrations. Changing this after
    | migration requires a new migration to alter the table structure.
    |
    */
    'primary_key_type' => env('FILEXUS_PRIMARY_KEY_TYPE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | File Collections Configuration
    |--------------------------------------------------------------------------
    |
    | Define default behavior and constraints for file collections.
    | These settings can be overridden per model or per collection.
    |
    */
    'collections' => [
        'default' => [
            'multiple' => true,
            'max_file_size' => 10240, // KB (10MB)
            'allowed_mimes' => [], // Empty array = allow all
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global File Constraints
    |--------------------------------------------------------------------------
    |
    | Global defaults for file uploads. Can be overridden per collection.
    |
    */
    'max_file_size' => 10240, // KB (10MB)
    'allowed_mimes' => [], // Empty array = allow all

    /*
    |--------------------------------------------------------------------------
    | Orphan Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Files are considered "orphaned" when their parent model no longer exists.
    | This setting defines how many hours must pass before an orphaned file
    | is eligible for deletion.
    |
    */
    'orphan_cleanup_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Path Generator
    |--------------------------------------------------------------------------
    |
    | The class responsible for generating file storage paths.
    | Must implement a generate() method that returns a string path.
    |
    */
    'path_generator' => \Filexus\Services\FilePathGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | File Deduplication
    |--------------------------------------------------------------------------
    |
    | When enabled, files with identical SHA256 hashes will reuse the same
    | storage location, saving disk space.
    |
    */
    'deduplicate' => false,

    /*
    |--------------------------------------------------------------------------
    | Generate Thumbnails
    |--------------------------------------------------------------------------
    |
    | Automatically generate thumbnails for image uploads.
    | Requires intervention/image package.
    |
    */
    'generate_thumbnails' => false,

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Sizes
    |--------------------------------------------------------------------------
    |
    | Define thumbnail dimensions when generate_thumbnails is enabled.
    |
    */
    'thumbnail_sizes' => [
        'small' => [150, 150],
        'medium' => [300, 300],
        'large' => [600, 600],
    ],
];
