# File Pruning

Filexus provides automated cleanup of expired and orphaned files through the prune command.

## The Prune Command

Clean up files automatically:

```bash
php artisan filexus:prune
```

## Command Options

### Prune Both Expired and Orphaned

Default behavior prunes both types:

```bash
php artisan filexus:prune
```

### Prune Only Expired Files

Files that have passed their `expires_at` date:

```bash
php artisan filexus:prune --expired
```

### Prune Only Orphaned Files

Files whose parent model no longer exists:

```bash
php artisan filexus:prune --orphaned
```

### Custom Age for Orphans

Specify minimum age in hours before orphaned files are deleted:

```bash
php artisan filexus:prune --orphaned --hours-old=48
```

Default is 24 hours (configurable in `config/filexus.php`).

## What Gets Pruned

### Expired Files

Files where `expires_at` is in the past:

```php
// These will be pruned
$file->expires_at = now()->subDays(1); // Expired yesterday

// These won't be pruned yet
$file->expires_at = now()->addDays(7); // Expires in 7 days
$file->expires_at = null; // No expiration set
```

### Orphaned Files

Files whose parent model (`fileable`) no longer exists:

```php
// File's parent was deleted
$post = Post::find(123); // null (deleted)
$file->fileable_id = 123; // Points to non-existent post
$file->fileable_type = 'App\\Models\\Post';

// This file is orphaned and will be pruned after grace period
```

## Scheduling Automatic Pruning

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run daily at 2 AM
    $schedule->command('filexus:prune')
        ->dailyAt('02:00');

    // Or run hourly
    $schedule->command('filexus:prune')
        ->hourly();

    // Run weekly on Sunday
    $schedule->command('filexus:prune')
        ->weekly(0, '03:00'); // Sunday at 3 AM
}
```

### Different Schedules for Different Types

```php
protected function schedule(Schedule $schedule)
{
    // Prune expired files every hour
    $schedule->command('filexus:prune --expired')
        ->hourly();

    // Prune orphaned files daily
    $schedule->command('filexus:prune --orphaned')
        ->dailyAt('02:00');
}
```

## Configuration

Configure orphan cleanup grace period in `config/filexus.php`:

```php
'orphan_cleanup_hours' => 24, // Wait 24 hours before deleting orphans
```

## Manual Pruning

Use the manager directly in code:

```php
use Filexus\FilexusManager;

$manager = app(FilexusManager::class);

// Prune expired files
$expiredCount = $manager->pruneExpired();

// Prune orphaned files
$orphanedCount = $manager->pruneOrphaned(24); // 24 hours old

// Get statistics without pruning
$stats = $manager->getPruneStatistics();
// ['expired' => 5, 'potentially_orphaned' => 10, 'total' => 15]
```

## Command Output

The prune command provides detailed output:

```bash
$ php artisan filexus:prune

Pruning files...

✔ Deleted 5 expired files
✔ Deleted 3 orphaned files (older than 24 hours)

Total: 8 files deleted
```

When no files need pruning:

```bash
$ php artisan filexus:prune

No files to prune.
```

## Prune Statistics

Get statistics before running cleanup:

```php
use Filexus\FilexusManager;

$manager = app(FilexusManager::class);
$stats = $manager->getPruneStatistics();

echo "Expired files: " . $stats['expired'];
echo "Orphaned files: " . $stats['potentially_orphaned'];
echo "Total: " . $stats['total'];
```

## Safety Features

### Grace Period for Orphans

Orphaned files aren't deleted immediately:

```php
// File becomes orphaned at 10:00 AM
$post->delete(); // Post deleted, files become orphaned

// File won't be deleted until after grace period
// If grace period is 24 hours, file persists until 10:00 AM next day
```

### No Grace Period for Expired Files

Expired files are deleted immediately when prune runs:

```php
$file->expires_at = now()->subMinutes(1);
// Will be deleted on next prune run
```

## Logging

Add logging to track pruning activity:

```php
use Filexus\Events\FileDeleted;
use Illuminate\Support\Facades\Log;

Event::listen(FileDeleted::class, function (FileDeleted $event) {
    Log::channel('file_pruning')->info('File pruned', [
        'id' => $event->file->id,
        'name' => $event->file->original_name,
        'reason' => $event->file->isExpired() ? 'expired' : 'orphaned',
        'size' => $event->file->size,
    ]);
});
```

## Custom Prune Command

Create a custom command for specialized pruning:

```bash
php artisan make:command PruneUserFiles
```

```php
namespace App\Console\Commands;

use Filexus\Models\File;
use Illuminate\Console\Command;

class PruneUserFiles extends Command
{
    protected $signature = 'app:prune-user-files {user}';
    protected $description = 'Prune all files for a specific user';

    public function handle()
    {
        $userId = $this->argument('user');

        $files = File::where('fileable_type', 'App\\Models\\User')
            ->where('fileable_id', $userId)
            ->get();

        $count = 0;
        foreach ($files as $file) {
            $file->delete();
            $count++;
        }

        $this->info("Deleted {$count} files for user {$userId}");
    }
}
```

## Monitoring

Monitor disk space saved by pruning:

```php
use Filexus\Events\FileDeleted;

$totalSpaceFreed = 0;

Event::listen(FileDeleted::class, function (FileDeleted $event) use (&$totalSpaceFreed) {
    $totalSpaceFreed += $event->file->size;
});

// After pruning
echo "Space freed: " . format_bytes($totalSpaceFreed);
```

## Soft Delete Support

If your models use soft deletes, orphaned files persist until models are force-deleted:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFiles, SoftDeletes;
}

$post->delete(); // Soft delete - files remain
$post->forceDelete(); // Hard delete - files become orphaned
```

## Best Practices

1. **Schedule Regular Pruning**: Run at least daily
2. **Monitor Storage**: Track disk space trends
3. **Set Appropriate Grace Periods**: 24-48 hours for orphans
4. **Log Pruning Activity**: Audit what gets deleted
5. **Test in Staging**: Verify pruning logic before production
6. **Notify Users**: Warn users before expiration (don't rely solely on pruning)
7. **Backup Before Major Cleans**: Have recovery option for accidents

## Common Scenarios

### Clean Up Test Files

```bash
# Delete all files older than 30 days from test collection
php artisan tinker

File::where('collection', 'test')
    ->where('created_at', '<', now()->subDays(30))
    ->each->delete();
```

### Emergency Space Recovery

```bash
# Find and delete largest orphaned files first
php artisan tinker

File::whereOrphaned()
    ->orderBy('size', 'desc')
    ->limit(100)
    ->each->delete();
```

### Clean Specific Model Files

```bash
# Delete all files for deleted posts
php artisan tinker

File::where('fileable_type', 'App\\Models\\Post')
    ->whereDoesntHave('fileable')
    ->each->delete();
```
