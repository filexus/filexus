# Query Scopes

Filexus provides powerful query scopes to efficiently filter and retrieve files. These scopes work on both the `File` model and the `files()` relationship.

## File Model Scopes

Query the `File` model directly:

```php
use Filexus\Models\File;

$files = File::whereCollection('gallery')->get();
```

## Available Scopes

### whereCollection()

Filter files by collection name.

```php
// Get all files in 'gallery' collection
$gallery = File::whereCollection('gallery')->get();

// Get all avatars
$avatars = File::whereCollection('avatar')->get();
```

**Use Case:** Find all files of a specific type across all models:

```php
// All thumbnails in the system
$allThumbnails = File::whereCollection('thumbnail')
    ->with('fileable')
    ->get();
```

### whereExpired()

Get files that have expired.

```php
// Get all expired files
$expired = File::whereExpired()->get();

// Count expired files
$expiredCount = File::whereExpired()->count();

// Delete expired files
File::whereExpired()->each(function ($file) {
    $file->delete();
});
```

**Use Case:** Cleanup or reporting:

```php
// Generate expiration report
$expiredByCollection = File::whereExpired()
    ->get()
    ->groupBy('collection')
    ->map(fn($files) => $files->count());
```

### whereNotExpired()

Get files that have not expired.

```php
// Active files only
$active = File::whereNotExpired()->get();

// Files expiring soon (next 24 hours)
$expiringSoon = File::whereNotExpired()
    ->where('expires_at', '<=', now()->addDay())
    ->where('expires_at', '>', now())
    ->get();
```

### whereOrphaned()

Get files whose parent model no longer exists.

```php
// Find orphaned files
$orphaned = File::whereOrphaned()->get();

// Count orphans by type
$orphansByType = File::whereOrphaned()
    ->get()
    ->groupBy('fileable_type')
    ->map(fn($files) => $files->count());

// Delete orphans older than 7 days
File::whereOrphaned()
    ->where('created_at', '<', now()->subDays(7))
    ->each(fn($file) => $file->delete());
```

**Use Case:** Maintenance and cleanup:

```php
// Scheduled job to clean orphans
class CleanOrphanFiles extends Command
{
    public function handle()
    {
        $count = File::whereOrphaned()
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        File::whereOrphaned()
            ->where('created_at', '<', now()->subDays(7))
            ->each(fn($file) => $file->delete());

        $this->info("Deleted {$count} orphaned files");
    }
}
```

### whereMime()

Filter by MIME type.

```php
// All JPEG images
$jpegs = File::whereMime('image/jpeg')->get();

// All PDFs
$pdfs = File::whereMime('application/pdf')->get();
```

**Use Case:** Find specific file types:

```php
// All videos
$videos = File::where(function ($query) {
    $query->whereMime('video/mp4')
          ->orWhereMime('video/mpeg')
          ->orWhereMime('video/quicktime');
})->get();
```

### whereExtension()

Filter by file extension.

```php
// All JPG files
$jpgs = File::whereExtension('jpg')->get();

// All documents
$docs = File::whereIn('extension', ['pdf', 'docx', 'xlsx'])->get();
```

## Combining Scopes

Chain scopes for complex queries:

```php
// Get active gallery images
$activeGallery = File::whereCollection('gallery')
    ->whereNotExpired()
    ->whereMime('image/jpeg')
    ->get();

// Expired documents
$expiredDocs = File::whereCollection('documents')
    ->whereExpired()
    ->get();

// Large orphaned files (over 10MB)
$largeOrphans = File::whereOrphaned()
    ->where('size', '>', 10 * 1024 * 1024)
    ->orderBy('size', 'desc')
    ->get();
```

## Using Scopes on Relationships

Apply scopes to the `files()` relationship:

```php
$post = Post::find(1);

// Get expired files for this post
$expired = $post->files()
    ->whereExpired()
    ->get();

// Get images only
$images = $post->files('gallery')
    ->whereMime('image/jpeg')
    ->orWhereMime('image/png')
    ->get();
```

## Model Scopes

Use scopes to query models that have files:

### whereHasFile()

Find models with files in a specific collection:

```php
// Posts with thumbnails
$postsWithThumbs = Post::whereHasFile('thumbnail')->get();

// Users with avatars
$usersWithAvatars = User::whereHasFile('avatar')->get();
```

### whereDoesntHaveFile()

Find models without files in a collection:

```php
// Posts without thumbnails
$postsWithoutThumbs = Post::whereDoesntHaveFile('thumbnail')->get();

// Products without images
$productsWithoutImages = Product::whereDoesntHaveFile('images')->get();
```

**Use Case:** Data quality checks:

```php
// Find incomplete products
$incomplete = Product::query()
    ->whereDoesntHaveFile('images')
    ->orWhereDoesntHaveFile('documents')
    ->get();
```

## Custom Scopes

Add your own scopes to the File model:

```php
namespace App\Models;

use Filexus\Models\File as BaseFile;

class File extends BaseFile
{
    public function scopeImages($query)
    {
        return $query->where('mime', 'like', 'image/%');
    }

    public function scopeVideos($query)
    {
        return $query->where('mime', 'like', 'video/%');
    }

    public function scopeLargerThan($query, $sizeInKB)
    {
        return $query->where('size', '>', $sizeInKB * 1024);
    }

    public function scopeUploadedBy($query, $userId)
    {
        return $query->whereJsonContains('metadata->uploaded_by', $userId);
    }
}
```

Use custom scopes:

```php
// All images
$images = File::images()->get();

// Videos uploaded by user
$userVideos = File::videos()
    ->uploadedBy(auth()->id())
    ->get();

// Large files (over 5MB)
$largeFiles = File::largerThan(5 * 1024)
    ->orderBy('size', 'desc')
    ->get();
```

## Practical Examples

### Cleanup Dashboard

```php
class FileCleanupController extends Controller
{
    public function dashboard()
    {
        return view('admin.cleanup', [
            'expired_count' => File::whereExpired()->count(),
            'orphaned_count' => File::whereOrphaned()->count(),
            'total_files' => File::count(),
            'total_size' => File::sum('size'),
            'collections' => File::select('collection')
                ->groupBy('collection')
                ->get()
                ->pluck('collection'),
        ]);
    }

    public function cleanupExpired()
    {
        $count = File::whereExpired()->count();

        File::whereExpired()->each(fn($file) => $file->delete());

        return back()->with('success', "Deleted {$count} expired files");
    }
}
```

### File Analytics

```php
class FileAnalyticsService
{
    public function getStatistics()
    {
        return [
            'total_files' => File::count(),
            'total_size' => File::sum('size'),
            'by_collection' => File::select('collection')
                ->selectRaw('count(*) as count, sum(size) as total_size')
                ->groupBy('collection')
                ->get(),
            'by_type' => File::select('mime')
                ->selectRaw('count(*) as count')
                ->groupBy('mime')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'expired' => File::whereExpired()->count(),
            'orphaned' => File::whereOrphaned()->count(),
            'expiring_soon' => File::whereNotExpired()
                ->where('expires_at', '<=', now()->addDays(7))
                ->count(),
        ];
    }
}
```

### Storage Report

```php
class StorageReportCommand extends Command
{
    protected $signature = 'files:report';

    public function handle()
    {
        $this->info('File Storage Report');
        $this->info('==================');

        $totalFiles = File::count();
        $totalSize = File::sum('size');

        $this->info("Total Files: {$totalFiles}");
        $this->info("Total Size: " . $this->formatBytes($totalSize));

        $this->newLine();
        $this->info('By Collection:');

        File::select('collection')
            ->selectRaw('count(*) as count, sum(size) as size')
            ->groupBy('collection')
            ->orderBy('size', 'desc')
            ->get()
            ->each(function ($stat) {
                $size = $this->formatBytes($stat->size);
                $this->line("  {$stat->collection}: {$stat->count} files ({$size})");
            });

        $expired = File::whereExpired()->count();
        $orphaned = File::whereOrphaned()->count();

        if ($expired > 0 || $orphaned > 0) {
            $this->newLine();
            $this->warn("Cleanup Needed:");
            if ($expired > 0) {
                $this->line("  Expired: {$expired} files");
            }
            if ($orphaned > 0) {
                $this->line("  Orphaned: {$orphaned} files");
            }
        }
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### Find Duplicates

```php
// Find files with duplicate hashes
$duplicates = File::select('hash')
    ->selectRaw('count(*) as count')
    ->groupBy('hash')
    ->having('count', '>', 1)
    ->get();

foreach ($duplicates as $duplicate) {
    $files = File::where('hash', $duplicate->hash)->get();

    echo "Duplicate files (hash: {$duplicate->hash}):";
    foreach ($files as $file) {
        echo "  - {$file->original_name} ({$file->id})";
    }
}
```

## Performance Tips

1. **Eager Loading**: Load relationships when querying files:
```php
$files = File::with('fileable')->whereCollection('gallery')->get();
```

2. **Select Specific Columns**: Only get what you need:
```php
$files = File::select('id', 'path', 'mime')->whereCollection('images')->get();
```

3. **Use Chunking**: For large datasets:
```php
File::whereExpired()->chunk(100, function ($files) {
    foreach ($files as $file) {
        $file->delete();
    }
});
```

4. **Index Usage**: Ensure indexes exist for commonly queried columns (collection, expires_at, hash).

## See Also

- [File Model API](/api/file-model) - Complete File model reference
- [Basic Operations](/usage/basic) - Basic file operations
- [File Pruning](/usage/pruning) - Automated cleanup
