# File Deduplication

Filexus automatically generates SHA-256 hashes for all uploaded files, enabling powerful deduplication strategies to save storage space and bandwidth.

## Automatic Deduplication

Filexus includes **built-in automatic deduplication** that can be enabled via configuration. When enabled, files with identical content (same SHA-256 hash) will automatically share the same physical storage, saving disk space.

### Enable Deduplication

Set in `config/filexus.php`:

```php
'deduplicate' => true,
```

Or via environment variable:

```env
FILEXUS_DEDUPLICATE=true
```

### How Automatic Deduplication Works

When deduplication is enabled:

1. **First Upload**: File is uploaded and stored normally
2. **Duplicate Upload**: If a file with the same hash exists, the physical file is reused
3. **Multiple Records**: Each model gets its own `File` record, but they share the physical file
4. **Smart Deletion**: Physical file is only deleted when the last reference is removed

**Example:**

```php
// Enable deduplication
config(['filexus.deduplicate' => true]);

// User 1 uploads a file
$file1 = $user1->attach('avatar', $request->file('avatar'));

// User 2 uploads the same file (same content)
$file2 = $user2->attach('avatar', $request->file('avatar'));

// Both records point to the same physical file
assert($file1->path === $file2->path);

// Metadata indicates deduplication
assert($file1->metadata['deduplicated'] === false);  // Original
assert($file2->metadata['deduplicated'] === true);   // Duplicate
assert($file2->metadata['original_file_id'] === $file1->id);

// Deleting $file2 won't delete the physical file
// Physical file is only deleted when $file1 is also deleted
```

### Benefits

- **Space Savings**: Identical files share the same storage
- **Automatic**: No manual checks required
- **Cross-Model**: Works across different models and collections
- **Safe**: Reference counting prevents premature deletion
- **Transparent**: Each model still gets its own File record

### Metadata Tracking

Deduplicated files include metadata:

```php
$file = $post->attach('documents', $uploadedFile);

if ($file->metadata['deduplicated'] ?? false) {
    // This file reuses an existing physical file
    $originalId = $file->metadata['original_file_id'];
    echo "This file shares storage with file #{$originalId}";
}
```

## Manual Deduplication

For more control, you can also implement manual deduplication strategies.

### Check Before Upload

```php
use Filexus\FilexusManager;

$manager = app(FilexusManager::class);

// Calculate hash from uploaded file
$hash = hash_file('sha256', $request->file('document')->path());

// Check if file already exists
if ($manager->hashExists($hash)) {
    $existing = $manager->findByHash($hash);

    // Reuse existing file
    return response()->json([
        'message' => 'File already exists',
        'file_id' => $existing->id,
        'duplicate' => true,
    ]);
}

// Upload new file
$file = $post->attach('documents', $request->file('document'));
```

### Deduplication Service

Create a reusable service:

```php
namespace App\Services;

use Filexus\FilexusManager;
use Illuminate\Http\UploadedFile;

class DeduplicationService
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function uploadOrReuse($model, string $collection, UploadedFile $file)
    {
        // Calculate hash
        $hash = hash_file('sha256', $file->path());

        // Check for existing file
        if ($this->manager->hashExists($hash)) {
            $existing = $this->manager->findByHash($hash);

            Log::info('File deduplicated', [
                'hash' => $hash,
                'existing_id' => $existing->id,
                'original_name' => $file->getClientOriginalName(),
            ]);

            return [
                'file' => $existing,
                'deduplicated' => true,
            ];
        }

        // Upload new file
        $newFile = $this->manager->upload($model, $collection, $file);

        return [
            'file' => $newFile,
            'deduplicated' => false,
        ];
    }
}
```

Usage:

```php
$service = app(DeduplicationService::class);

$result = $service->uploadOrReuse($post, 'documents', $request->file('doc'));

if ($result['deduplicated']) {
    return response()->json([
        'message' => 'File already exists, using existing copy',
        'file' => $result['file'],
    ]);
}

return response()->json([
    'message' => 'File uploaded successfully',
    'file' => $result['file'],
]);
```

## Shared File References

For true deduplication, you can create multiple references to the same physical file:

```php
namespace App\Services;

use Filexus\Models\File;

class SharedFileService
{
    public function attachSharedFile($model, string $collection, File $existingFile)
    {
        // Create new File record pointing to same physical file
        return File::create([
            'disk' => $existingFile->disk,
            'path' => $existingFile->path,
            'collection' => $collection,
            'fileable_type' => get_class($model),
            'fileable_id' => $model->getKey(),
            'original_name' => $existingFile->original_name,
            'mime' => $existingFile->mime,
            'extension' => $existingFile->extension,
            'size' => $existingFile->size,
            'hash' => $existingFile->hash,
            'metadata' => [
                'shared_from' => $existingFile->id,
                'reference' => true,
            ],
        ]);
    }

    public function deleteSharedFile(File $file)
    {
        // Only delete physical file if no other references exist
        $referenceCount = File::where('hash', $file->hash)->count();

        if ($referenceCount <= 1) {
            // Last reference, delete physical file
            Storage::disk($file->disk)->delete($file->path);
        }

        // Always delete database record
        $file->delete();
    }
}
```

## Automatic Deduplication Strategy

### Upload Middleware

Create middleware to automatically deduplicate uploads:

```php
namespace App\Http\Middleware;

use Filexus\FilexusManager;

class DeduplicateFileUploads
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function handle($request, Closure $next)
    {
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $hash = hash_file('sha256', $file->path());

            if ($this->manager->hashExists($hash)) {
                $existing = $this->manager->findByHash($hash);

                // Attach existing file ID to request
                $request->merge(['existing_file_id' => $existing->id]);
            }
        }

        return $next($request);
    }
}
```

### Controller

```php
public function store(Request $request)
{
    if ($request->has('existing_file_id')) {
        // File already exists
        $file = File::find($request->existing_file_id);

        return response()->json([
            'message' => 'File already exists, using existing copy',
            'file' => $file,
            'deduplicated' => true,
            'space_saved' => $file->size,
        ]);
    }

    // Upload new file
    $file = $post->attach('documents', $request->file('document'));

    return response()->json([
        'message' => 'File uploaded',
        'file' => $file,
        'deduplicated' => false,
    ]);
}
```

## Finding Duplicates

### Find All Duplicate Files

```php
use Filexus\Models\File;

// Group files by hash
$duplicates = File::select('hash')
    ->selectRaw('count(*) as count, sum(size) as total_size')
    ->groupBy('hash')
    ->having('count', '>', 1)
    ->orderBy('total_size', 'desc')
    ->get();

foreach ($duplicates as $duplicate) {
    $files = File::where('hash', $duplicate->hash)->get();
    $wastedSpace = ($duplicate->count - 1) * $files->first()->size;

    echo "Hash: {$duplicate->hash}";
    echo "  Duplicates: {$duplicate->count}";
    echo "  Wasted Space: " . formatBytes($wastedSpace);

    foreach ($files as $file) {
        echo "    - {$file->original_name} (ID: {$file->id})";
    }
}
```

### Calculate Potential Savings

```php
class DeduplicationReportCommand extends Command
{
    protected $signature = 'files:dedup-report';

    public function handle()
    {
        $duplicates = File::select('hash')
            ->selectRaw('count(*) as count, size')
            ->groupBy('hash', 'size')
            ->having('count', '>', 1)
            ->get();

        $totalDuplicates = 0;
        $wastedSpace = 0;

        foreach ($duplicates as $dup) {
            $totalDuplicates += ($dup->count - 1);
            $wastedSpace += ($dup->count - 1) * $dup->size;
        }

        $this->info('Deduplication Report');
        $this->info('===================');
        $this->info("Duplicate Files: {$totalDuplicates}");
        $this->info("Wasted Space: " . $this->formatBytes($wastedSpace));
        $this->info("Potential Savings: " . $this->formatBytes($wastedSpace));
    }
}
```

## Deduplication on Upload Event

Automatically deduplicate using events:

```php
use Filexus\Events\FileUploading;
use Filexus\FilexusManager;

Event::listen(FileUploading::class, function (FileUploading $event) {
    $manager = app(FilexusManager::class);

    // Calculate hash before upload
    $hash = hash_file('sha256', $event->uploadedFile->path());

    // Check if duplicate exists
    if ($manager->hashExists($hash)) {
        $existing = $manager->findByHash($hash);

        Log::info('Duplicate file detected', [
            'hash' => $hash,
            'existing_id' => $existing->id,
            'new_name' => $event->uploadedFile->getClientOriginalName(),
        ]);

        // Could throw exception to prevent upload
        // throw new DuplicateFileException($existing);
    }
});
```

## Reference Counting

Implement reference counting for safe deletion:

```php
namespace App\Models;

use Filexus\Models\File as BaseFile;

class File extends BaseFile
{
    public function getReferenceCountAttribute()
    {
        return static::where('hash', $this->hash)->count();
    }

    public function isLastReference()
    {
        return $this->reference_count === 1;
    }

    protected static function booted()
    {
        static::deleting(function ($file) {
            // Only delete physical file if last reference
            if ($file->isLastReference()) {
                Storage::disk($file->disk)->delete($file->path);
            }
        });
    }
}
```

## Storage Optimization

### Cleanup Duplicates Command

```php
class DeduplicateFilesCommand extends Command
{
    protected $signature = 'files:deduplicate {--dry-run}';

    public function handle()
    {
        $duplicates = File::select('hash')
            ->selectRaw('count(*) as count')
            ->groupBy('hash')
            ->having('count', '>', 1)
            ->get();

        $savedSpace = 0;
        $filesProcessed = 0;

        foreach ($duplicates as $dup) {
            $files = File::where('hash', $dup->hash)
                ->orderBy('id')
                ->get();

            $original = $files->shift(); // Keep first

            foreach ($files as $duplicate) {
                if (!$this->option('dry-run')) {
                    // Update to point to original path
                    $duplicate->update(['path' => $original->path]);

                    // Delete duplicate physical file
                    if (Storage::exists($duplicate->path)) {
                        Storage::delete($duplicate->path);
                        $savedSpace += $duplicate->size;
                    }
                }

                $filesProcessed++;
            }
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: Would deduplicate {$filesProcessed} files");
            $this->info("Potential space savings: " . formatBytes($savedSpace));
        } else {
            $this->info("Deduplicated {$filesProcessed} files");
            $this->info("Space saved: " . formatBytes($savedSpace));
        }
    }
}
```

## Best Practices

1. **Check Early**: Calculate hash before uploading large files
2. **Log Deduplication**: Track savings and patterns
3. **User Feedback**: Inform users when files are deduplicated
4. **Reference Counting**: Track how many times a file is referenced
5. **Safe Deletion**: Only delete physical files when no references exist
6. **Metadata Tracking**: Store original uploader info even for shared files

## API Example

```php
public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:10240',
    ]);

    $file = $request->file('file');
    $hash = hash_file('sha256', $file->path());

    // Check for duplicate
    $existing = File::where('hash', $hash)->first();

    if ($existing) {
        return response()->json([
            'success' => true,
            'message' => 'File already exists',
            'file' => [
                'id' => $existing->id,
                'name' => $existing->original_name,
                'url' => $existing->url(),
                'size' => $existing->size,
            ],
            'deduplicated' => true,
            'space_saved' => $existing->human_readable_size,
        ]);
    }

    // Upload new file
    $newFile = auth()->user()->attach('uploads', $file);

    return response()->json([
        'success' => true,
        'message' => 'File uploaded successfully',
        'file' => [
            'id' => $newFile->id,
            'name' => $newFile->original_name,
            'url' => $newFile->url(),
            'size' => $newFile->size,
        ],
        'deduplicated' => false,
    ]);
}
```

## See Also

- [Using the Manager](/advanced/manager) - Manager operations
- [Events](/advanced/events) - Event-based deduplication
- [FilexusManager API](/api/manager) - Hash checking methods
