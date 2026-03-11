# API Reference: Manager

The `FilexusManager` provides centralized file management operations.

## FilexusManager

**Namespace:** `Filexus\FilexusManager`

The manager handles high-level file operations and coordination between services.

---

## File Operations

### upload()

Upload a file to a model.

```php
public function upload(
    Model $model,
    string $collection,
    UploadedFile|string $file,
    ?array $metadata = null
): File
```

**Parameters:**
- `$model` (Model): Parent model
- `$collection` (string): Collection name
- `$file` (UploadedFile|string): File to upload
- `$metadata` (array|null): Optional custom metadata

**Returns:** `File` - Created file model

**Example:**

```php
use Filexus\FilexusManager;

$manager = app(FilexusManager::class);

$file = $manager->upload(
    $post,
    'gallery',
    $request->file('image'),
    ['uploaded_by' => auth()->id()]
);
```

### delete()

Delete a file.

```php
public function delete(File $file): bool
```

**Parameters:**
- `$file` (File): File to delete

**Returns:** `bool` - True if deleted

**Behavior:**
- Removes file from disk storage
- Deletes database record
- Dispatches `FileDeleted` event

**Example:**

```php
$file = File::find($id);
$manager->delete($file);
```

---

## Pruning Operations

### prune Expired()

Remove all expired files.

```php
public function pruneExpired(): int
```

**Returns:** `int` - Number of files deleted

**Example:**

```php
$count = $manager->pruneExpired();
echo "Deleted {$count} expired files";
```

### pruneOrphaned()

Remove files whose parent models no longer exist.

```php
public function pruneOrphaned(int $hoursOld = 24): int
```

**Parameters:**
- `$hoursOld` (int): Minimum age in hours before deletion (default: 24)

**Returns:** `int` - Number of files deleted

**Example:**

```php
// Delete orphans older than 48 hours
$count = $manager->pruneOrphaned(48);
```

### getPruneStatistics()

Get statistics about files that can be pruned.

```php
public function getPruneStatistics(): array
```

**Returns:** `array` - Statistics with keys:
- `expired` (int): Number of expired files
- `potentially_orphaned` (int): Number of orphaned files
- `total` (int): Total files that can be pruned

**Example:**

```php
$stats = $manager->getPruneStatistics();

echo "Expired: {$stats['expired']}";
echo "Orphaned: {$stats['potentially_orphaned']}";
echo "Total: {$stats['total']}";
```

---

## Hash Operations

### hashExists()

Check if a file with a given hash exists.

```php
public function hashExists(string $hash): bool
```

**Parameters:**
- `$hash` (string): SHA-256 hash to check

**Returns:** `bool` - True if file exists

**Use Case:** Deduplication - check before uploading

**Example:**

```php
$hash = hash_file('sha256', $uploadedFile->path());

if ($manager->hashExists($hash)) {
    // File already exists, reuse it
    $existingFile = $manager->findByHash($hash);
} else {
    // Upload new file
    $file = $model->attach('documents', $uploadedFile);
}
```

### findByHash()

Find a file by its hash.

```php
public function findByHash(string $hash): ?File
```

**Parameters:**
- `$hash` (string): SHA-256 hash

**Returns:** `File|null` - File model or null if not found

**Example:**

```php
$file = $manager->findByHash($hash);

if ($file) {
    echo "Found: {$file->original_name}";
}
```

---

## Collection Operations

### getFilesByCollection()

Get files grouped by collection.

```php
public function getFilesByCollection(Model $model): Collection
```

**Parameters:**
- `$model` (Model): Parent model

**Returns:** `Collection` - Files grouped by collection name

**Example:**

```php
$grouped = $manager->getFilesByCollection($post);

foreach ($grouped as $collection => $files) {
    echo "Collection: {$collection}";
    foreach ($files as $file) {
        echo "- {$file->original_name}";
    }
}
```

### countFiles()

Count files for a model.

```php
public function countFiles(Model $model, ?string $collection = null): int
```

**Parameters:**
- `$model` (Model): Parent model
- `$collection` (string|null): Optional collection filter

**Returns:** `int` - File count

**Example:**

```php
// Count all files
$total = $manager->countFiles($post);

// Count in specific collection
$imageCount = $manager->countFiles($post, 'gallery');
```

---

## Path Operations

### getFilePath()

Get the full storage path for a file.

```php
public function getFilePath(File $file): string
```

**Parameters:**
- `$file` (File): File model

**Returns:** `string` - Absolute path on disk

**Example:**

```php
$fullPath = $manager->getFilePath($file);
$content = file_get_contents($fullPath);
```

### generateUniquePath()

Generate a unique storage path for a new file.

```php
public function generateUniquePath(
    Model $model,
    string $collection,
    string $extension
): string
```

**Parameters:**
- `$model` (Model): Parent model
- `$collection` (string): Collection name
- `$extension` (string): File extension

**Returns:** `string` - Unique relative path

**Pattern:** `{model}/{id}/{collection}/{uuid}.{ext}`

**Example:**

```php
$path = $manager->generateUniquePath($post, 'images', 'jpg');
// "posts/123/images/abc123def456.jpg"
```

---

## Validation

### validateCollection()

Validate collection configuration.

```php
public function validateCollection(
    Model $model,
    string $collection,
    UploadedFile $file
): void
```

**Parameters:**
- `$model` (Model): Parent model
- `$collection` (string): Collection name
- `$file` (UploadedFile): File to validate

**Throws:**
- `InvalidCollectionException` - If validation fails
- `FileSizeException` - If file exceeds max size
- `InvalidMimeTypeException` - If MIME type not allowed

**Checks:**
- File size vs max_file_size
- MIME type vs allowed_mimes
- Single file collection already has file

**Example:**

```php
try {
    $manager->validateCollection($post, 'avatar', $uploadedFile);
    // Validation passed, proceed with upload
} catch (InvalidCollectionException $e) {
    return back()->withErrors(['file' => $e->getMessage()]);
}
```

---

## Storage Operations

### deleteFromStorage()

Delete file from storage disk.

```php
public function deleteFromStorage(File $file): bool
```

**Parameters:**
- `$file` (File): File to delete

**Returns:** `bool` - True if deleted

**Example:**

```php
if ($manager->deleteFromStorage($file)) {
    echo "File deleted from storage";
}
```

### fileExists()

Check if file exists in storage.

```php
public function fileExists(File $file): bool
```

**Parameters:**
- `$file` (File): File to check

**Returns:** `bool` - True if exists

**Example:**

```php
if (!$manager->fileExists($file)) {
    // File missing from storage
    Log::error("Missing file: {$file->id}");
}
```

---

## Usage Example

### Complete File Management Service

```php
namespace App\Services;

use Filexus\FilexusManager;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function uploadDocument($model, UploadedFile $file, array $metadata = [])
    {
        // Check for duplicate
        $hash = hash_file('sha256', $file->path());

        if ($this->manager->hashExists($hash)) {
            $existing = $this->manager->findByHash($hash);

            // Reuse existing file
            return $existing;
        }

        // Upload new file
        return $this->manager->upload($model, 'documents', $file, $metadata);
    }

    public function cleanup()
    {
        $expiredCount = $this->manager->pruneExpired();
        $orphanedCount = $this->manager->pruneOrphaned(48);

        return [
            'expired' => $expiredCount,
            'orphaned' => $orphanedCount,
            'total' => $expiredCount + $orphanedCount,
        ];
    }

    public function getStatistics()
    {
        return $this->manager->getPruneStatistics();
    }
}
```

### Deduplication Strategy

```php
public function uploadWithDeduplication(Model $model, UploadedFile $file)
{
    $manager = app(FilexusManager::class);

    // Calculate hash
    $hash = hash_file('sha256', $file->path());

    // Check if file already uploaded
    if ($manager->hashExists($hash)) {
        $existingFile = $manager->findByHash($hash);

        Log::info("Reusing existing file", [
            'hash' => $hash,
            'existing_id' => $existingFile->id,
        ]);

        // Create new reference to existing file
        // (Custom implementation needed)
        return $existingFile;
    }

    // Upload new file
    return $manager->upload($model, 'documents', $file);
}
```

---

## Accessing the Manager

### Via Dependency Injection

```php
use Filexus\FilexusManager;

class FileController
{
    public function __construct(
        private FilexusManager $manager
    ) {}

    public function upload(Request $request)
    {
        $file = $this->manager->upload(
            auth()->user(),
            'uploads',
            $request->file('file')
        );

        return response()->json(['file' => $file]);
    }
}
```

### Via Service Container

```php
$manager = app(FilexusManager::class);

$file = $manager->upload($model, 'documents', $uploadedFile);
```

### Via Facade (if implemented)

```php
use Filexus\Facades\Filexus;

$file = Filexus::upload($model, 'documents', $uploadedFile);
```
