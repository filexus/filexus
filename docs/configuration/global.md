# Global Configuration

Configure Filexus globally using the `config/filexus.php` file.

## Configuration File

After publishing the configuration file, you'll find it at `config/filexus.php`:

```php
return [
    'default_disk' => env('FILEXUS_DISK', 'public'),
    'primary_key_type' => env('FILEXUS_PRIMARY_KEY_TYPE', 'id'),
    'collections' => [
        'default' => [
            'multiple' => true,
            'max_file_size' => 10240,
            'allowed_mimes' => [],
        ],
    ],
    'orphan_cleanup_hours' => 24,
    'max_file_size' => 10240,
    'allowed_mimes' => [],
    'path_generator' => \Filexus\Services\FilePathGenerator::class,
];
```

## Options

### default_disk

The default storage disk for file uploads.

```php
'default_disk' => env('FILEXUS_DISK', 'public'),
```

**Options**: Any disk configured in `config/filesystems.php`

**Environment Variable**: `FILEXUS_DISK`

**Example**:
```env
FILEXUS_DISK=s3
```

### primary_key_type

The primary key type for the `files` table. Must be set before migrations.

```php
'primary_key_type' => env('FILEXUS_PRIMARY_KEY_TYPE', 'id'),
```

**Options**: `'id'`, `'uuid'`, `'ulid'`

**Default**: `'id'` (auto-increment integer)

**Environment Variable**: `FILEXUS_PRIMARY_KEY_TYPE`

See [Primary Key Types](/configuration/primary-keys) for details.

### collections

Default configuration for file collections.

```php
'collections' => [
    'default' => [
        'multiple' => true,
        'max_file_size' => 10240, // KB
        'allowed_mimes' => [],
    ],
],
```

#### multiple

Whether the collection accepts multiple files.

- `true`: Multiple files can be attached
- `false`: Only one file allowed (new uploads replace existing)

#### max_file_size

Maximum file size in kilobytes.

- `10240` = 10MB
- `0` = No limit (not recommended)

#### allowed_mimes

Array of allowed MIME types.

```php
'allowed_mimes' => [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
],
```

Empty array (`[]`) allows all file types.

### orphan_cleanup_hours

Hours before orphaned files are considered for cleanup.

```php
'orphan_cleanup_hours' => 24,
```

Orphaned files are those whose parent model no longer exists. This setting determines how old they must be before the prune command deletes them.

### max_file_size

Global maximum file size in kilobytes (fallback if not specified in collection config).

```php
'max_file_size' => 10240, // 10MB
```

### allowed_mimes

Global allowed MIME types (fallback if not specified in collection config).

```php
'allowed_mimes' => [],
```

### path_generator

The class responsible for generating file storage paths.

```php
'path_generator' => \Filexus\Services\FilePathGenerator::class,
```

See [Custom Path Generator](/configuration/custom-path) for creating your own.

### deduplicate

Enable automatic file deduplication based on SHA-256 hash.

```php
'deduplicate' => false,
```

When enabled:
- Files with identical content (same hash) share the same physical storage
- Each model still gets its own File record
- Physical file is only deleted when the last reference is removed
- Saves storage space for duplicate files

**Environment Variable**: `FILEXUS_DEDUPLICATE`

See [File Deduplication](/advanced/deduplication) for details.

### generate_thumbnails

Enable automatic thumbnail generation for image uploads.

```php
'generate_thumbnails' => false,
```

**Requirements**: Requires the `intervention/image` package to be installed.

```bash
composer require intervention/image
```

**Note**: If intervention/image is not installed, thumbnail generation will be gracefully skipped without errors.

See [Image Thumbnails](/advanced/thumbnails) for details.

### thumbnail_sizes

Define thumbnail dimensions when `generate_thumbnails` is enabled.

```php
'thumbnail_sizes' => [
    'small' => [150, 150],
    'medium' => [300, 300],
    'large' => [600, 600],
],
```

Array format: `'size_name' => [width, height]`

Thumbnails are generated using Intervention Image's `fit()` method, which:
- Crops and resizes to exact dimensions
- Centers the crop
- Maintains aspect ratio

See [Image Thumbnails](/advanced/thumbnails) for details.

## Environment Variables

Configure via `.env`:

```env
# Storage disk
FILEXUS_DISK=public

# Primary key type (must set before migration)
FILEXUS_PRIMARY_KEY_TYPE=id

# Deduplication
FILEXUS_DEDUPLICATE=true

# Thumbnails
FILEXUS_GENERATE_THUMBNAILS=true
```

## Named Collections

Define specific configurations for named collections:

```php
'collections' => [
    'default' => [
        'multiple' => true,
        'max_file_size' => 10240,
        'allowed_mimes' => [],
    ],

    'avatar' => [
        'multiple' => false,
        'max_file_size' => 2048, // 2MB
        'allowed_mimes' => ['image/jpeg', 'image/png'],
    ],

    'documents' => [
        'multiple' => true,
        'max_file_size' => 20480, // 20MB
        'allowed_mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],
],
```

## Configuration Priority

Configuration is resolved in this order:

1. **Per-model collection configuration** (highest priority)
2. **Global named collection configuration**
3. **Global default collection configuration** (lowest priority)

Example:

```php
// config/filexus.php
'collections' => [
    'default' => [
        'max_file_size' => 10240,
    ],
    'avatar' => [
        'max_file_size' => 2048,
    ],
],

// Post model
protected $fileCollections = [
    'avatar' => [
        'max_file_size' => 3072, // This takes precedence
    ],
];
```

## Validation Examples

### Image Collections

```php
'profile_pictures' => [
    'multiple' => false,
    'max_file_size' => 2048,
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],
],
```

### Document Collections

```php
'legal_documents' => [
    'multiple' => true,
    'max_file_size' => 10240,
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],
],
```

### Video Collections

```php
'videos' => [
    'multiple' => true,
    'max_file_size' => 102400, // 100MB
    'allowed_mimes' => [
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
    ],
],
```

## Clearing Configuration Cache

After changing configuration, clear the cache:

```bash
php artisan config:clear
```

In production, rebuild the cache:

```bash
php artisan config:cache
```
