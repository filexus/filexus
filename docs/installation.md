# Installation

This guide will walk you through installing and setting up Filexus in your Laravel application.

## Requirements

Before installing Filexus, ensure your system meets these requirements:

- **PHP**: 8.3 or higher
- **Laravel**: 11 or higher
- **Database**: MySQL 5.7+, PostgreSQL 9.6+, SQLite 3.8.8+, or SQL Server 2017+

## Install via Composer

Install Filexus using Composer:

```bash
composer require filexus/filexus
```

## Publish Configuration

Publish the configuration file to customize Filexus settings:

```bash
php artisan vendor:publish --tag=filexus-config
```

This creates `config/filexus.php` in your application.

## Publish Migrations

Publish the migration files:

```bash
php artisan vendor:publish --tag=filexus-migrations
```

This publishes the `create_files_table` migration to your `database/migrations` directory.

::: tip Primary Key Configuration
If you want to use UUIDs or ULIDs instead of auto-increment IDs, configure this **before running migrations**. See [Primary Key Types](/configuration/primary-keys) for details.
:::

## Run Migrations

Run the migrations to create the `files` table:

```bash
php artisan migrate
```

## Configure Storage

Filexus uses Laravel's filesystem configuration. Ensure your `config/filesystems.php` has the disks you want to use:

```php
// config/filesystems.php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],

    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
],
```

If using the `public` disk, link storage:

```bash
php artisan storage:link
```

## Environment Variables

Add these optional environment variables to your `.env` file:

```env
# Storage disk (default: public)
FILEXUS_DISK=public

# Primary key type: id, uuid, or ulid (default: id)
FILEXUS_PRIMARY_KEY_TYPE=id
```

## Verify Installation

Verify Filexus is working by testing the core functionality. Create a test model or use `php artisan tinker`:

```php
use App\Models\Post; // Any model with HasFiles trait
use Illuminate\Http\UploadedFile;

// Create a test file
$testFile = UploadedFile::fake()->image('test.jpg');

// Create or find a model
$post = Post::first();

// Attach the file
$file = $post->attach('test', $testFile);

// Verify the file was attached
echo $file->original_name; // "test.jpg"
echo $file->url();         // Full URL to the file

// Retrieve the file
$retrieved = $post->file('test');
echo $retrieved->id === $file->id ? '✓ Working!' : '✗ Failed';

// Clean up
$post->detach('test', $file->id);
```

If you see "✓ Working!", Filexus is properly installed and configured.

## Next Steps

Now that Filexus is installed, continue to [Quick Start](/quick-start) to learn how to use it.

## Troubleshooting

### Migration Errors

If you encounter migration errors:

1. **Table already exists**: Drop the `files` table and re-run migrations
2. **Foreign key constraints**: Ensure your database supports the chosen primary key type

### Storage Permissions

If files fail to upload:

1. Check directory permissions: `storage/app/public` should be writable
2. Verify symlink: `public/storage` should link to `storage/app/public`
3. Run: `php artisan storage:link`

### Configuration Not Loading

If configuration changes don't take effect:

```bash
php artisan config:clear
php artisan cache:clear
```
