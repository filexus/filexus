# Using the Manager

The `FilexusManager` provides a centralized service for file operations, offering an alternative to the trait methods when you need more control or want to work with files independently of models.

## When to Use the Manager

Use the `HasFiles` trait methods for most operations:
```php
$post->attach('gallery', $file); // Recommended
```

Use the Manager directly when:
- Building custom file handling services
- Implementing file operations outside model context
- Checking for duplicates before upload
- Performing bulk operations
- Accessing pruning functionality programmatically

## Accessing the Manager

### Via Dependency Injection

```php
use Filexus\FilexusManager;

class FileService
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function handleUpload($model, $file)
    {
        return $this->manager->upload($model, 'documents', $file);
    }
}
```

### Via Service Container

```php
$manager = app(FilexusManager::class);

$file = $manager->upload($post, 'gallery', $uploadedFile);
```

### In Controllers

```php
use Filexus\FilexusManager;

class FileController extends Controller
{
    public function store(Request $request, FilexusManager $manager)
    {
        $file = $manager->upload(
            auth()->user(),
            'uploads',
            $request->file('document')
        );

        return response()->json(['file' => $file]);
    }
}
```

## Common Operations

### Upload Files

```php
$manager = app(FilexusManager::class);

// Upload with custom metadata
$file = $manager->upload($model, 'documents', $uploadedFile, [
    'uploaded_by' => auth()->id(),
    'category' => 'invoice',
    'year' => 2024,
]);
```

### Delete Files

```php
$file = File::find($fileId);
$manager->delete($file);
```

### Check File Existence

```php
if ($manager->fileExists($file)) {
    // File exists in storage
}
```

### Get Storage Path

```php
$fullPath = $manager->getFilePath($file);
$content = file_get_contents($fullPath);
```

## Deduplication

Check if a file already exists before uploading:

```php
$hash = hash_file('sha256', $uploadedFile->path());

if ($manager->hashExists($hash)) {
    // File already exists
    $existingFile = $manager->findByHash($hash);

    // Reuse existing file or link to it
    return $existingFile;
}

// Upload new file
$file = $manager->upload($model, 'documents', $uploadedFile);
```

## Pruning Operations

### Prune Expired Files

```php
$deletedCount = $manager->pruneExpired();

Log::info("Pruned {$deletedCount} expired files");
```

### Prune Orphaned Files

```php
// Delete orphans older than 48 hours
$deletedCount = $manager->pruneOrphaned(48);

Log::info("Pruned {$deletedCount} orphaned files");
```

### Get Prune Statistics

```php
$stats = $manager->getPruneStatistics();

return response()->json([
    'expired' => $stats['expired'],
    'orphaned' => $stats['potentially_orphaned'],
    'total' => $stats['total'],
]);
```

## Building Custom Services

### Document Upload Service

```php
namespace App\Services;

use Filexus\FilexusManager;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function uploadWithDeduplication($model, UploadedFile $file)
    {
        // Calculate hash
        $hash = hash_file('sha256', $file->path());

        // Check for duplicate
        if ($this->manager->hashExists($hash)) {
            $existing = $this->manager->findByHash($hash);

            Log::info("Reusing existing file", [
                'hash' => $hash,
                'file_id' => $existing->id,
            ]);

            return $existing;
        }

        // Upload new file
        return $this->manager->upload($model, 'documents', $file, [
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now()->toISOString(),
        ]);
    }

    public function cleanup()
    {
        $expired = $this->manager->pruneExpired();
        $orphaned = $this->manager->pruneOrphaned();

        return [
            'expired' => $expired,
            'orphaned' => $orphaned,
            'total' => $expired + $orphaned,
        ];
    }
}
```

### Bulk File Operations

```php
namespace App\Services;

use Filexus\FilexusManager;
use Illuminate\Support\Collection;

class BulkFileService
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function uploadMultiple($model, array $files, string $collection)
    {
        $uploaded = [];

        foreach ($files as $file) {
            $uploaded[] = $this->manager->upload($model, $collection, $file);
        }

        return collect($uploaded);
    }

    public function deleteByCollection($model, string $collection)
    {
        $files = $model->files($collection)->get();
        $count = 0;

        foreach ($files as $file) {
            $this->manager->delete($file);
            $count++;
        }

        return $count;
    }

    public function moveToNewCollection($file, string $newCollection)
    {
        $file->collection = $newCollection;
        $file->save();

        return $file;
    }
}
```

## Validation

Validate before upload:

```php
try {
    $manager->validateCollection($model, 'avatar', $uploadedFile);

    // Validation passed, proceed
    $file = $manager->upload($model, 'avatar', $uploadedFile);

} catch (InvalidCollectionException $e) {
    return back()->withErrors(['file' => $e->getMessage()]);
} catch (FileSizeException $e) {
    return back()->withErrors(['file' => 'File is too large']);
} catch (InvalidMimeTypeException $e) {
    return back()->withErrors(['file' => 'Invalid file type']);
}
```

## Path Generation

Generate custom paths:

```php
// Get path for new file
$path = $manager->generateUniquePath($post, 'images', 'jpg');
// "posts/123/images/abc-def-123.jpg"

// Use for custom upload logic
Storage::disk('s3')->put($path, $fileContent);
```

## Collection Management

### Get Files by Collection

```php
$grouped = $manager->getFilesByCollection($post);

foreach ($grouped as $collection => $files) {
    echo "Collection: {$collection}";
    foreach ($files as $file) {
        echo "- {$file->original_name}";
    }
}
```

### Count Files

```php
// All files
$total = $manager->countFiles($post);

// Specific collection
$imageCount = $manager->countFiles($post, 'gallery');
```

## Advanced Example: Media Library

```php
namespace App\Services;

use Filexus\FilexusManager;
use Filexus\Models\File;

class MediaLibraryService
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function uploadToLibrary(UploadedFile $file, array $metadata = [])
    {
        $library = MediaLibrary::first(); // Singleton model

        // Check for duplicate
        $hash = hash_file('sha256', $file->path());

        if ($this->manager->hashExists($hash)) {
            return $this->manager->findByHash($hash);
        }

        // Upload with metadata
        return $this->manager->upload($library, 'media', $file, array_merge($metadata, [
            'hash' => $hash,
            'uploaded_by' => auth()->id(),
            'is_public' => false,
        ]));
    }

    public function search(array $criteria)
    {
        $query = File::query();

        if (isset($criteria['collection'])) {
            $query->whereCollection($criteria['collection']);
        }

        if (isset($criteria['mime'])) {
            $query->whereMime($criteria['mime']);
        }

        if (isset($criteria['min_size'])) {
            $query->where('size', '>=', $criteria['min_size']);
        }

        return $query->get();
    }

    public function cleanupOldMedia(int $daysOld = 90)
    {
        $files = File::where('created_at', '<', now()->subDays($daysOld))
            ->whereCollection('media')
            ->get();

        $count = 0;
        foreach ($files as $file) {
            $this->manager->delete($file);
            $count++;
        }

        return $count;
    }
}
```

## Manager vs Trait Methods

| Operation  | Manager                                        | Trait Method                           |
| ---------- | ---------------------------------------------- | -------------------------------------- |
| Upload     | `$manager->upload($model, $collection, $file)` | `$model->attach($collection, $file)`   |
| Delete     | `$manager->delete($file)`                      | `$model->detach($collection, $fileId)` |
| Check Hash | `$manager->hashExists($hash)`                  | N/A                                    |
| Prune      | `$manager->pruneExpired()`                     | N/A                                    |
| Validate   | `$manager->validateCollection(...)`            | Automatic                              |

**Recommendation:** Use trait methods for simplicity, use manager for advanced scenarios.

## Best Practices

1. **Dependency Injection**: Always inject the manager via constructor
2. **Service Layer**: Wrap complex logic in dedicated service classes
3. **Error Handling**: Catch and handle exceptions appropriately
4. **Logging**: Log important operations for debugging
5. **Testing**: Mock the manager in tests for isolated unit testing

## Testing with the Manager

```php
use Filexus\FilexusManager;

it('uploads file via manager', function () {
    Storage::fake('public');

    $manager = app(FilexusManager::class);
    $post = Post::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $uploaded = $manager->upload($post, 'images', $file);

    expect($uploaded)->toBeInstanceOf(File::class);
    expect($uploaded->original_name)->toBe('test.jpg');
    Storage::disk('public')->assertExists($uploaded->path);
});
```

## See Also

- [FilexusManager API Reference](/api/manager) - Complete API documentation
- [File Deduplication](/advanced/deduplication) - Deduplication strategies
- [Events](/advanced/events) - Event system integration
