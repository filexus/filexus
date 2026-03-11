# API Reference: Trait Methods

Complete reference for the `HasFiles` trait methods.

## HasFiles Trait

Add file attachment capabilities to any Eloquent model.

### Usage

```php
use Filexus\Traits\HasFiles;

class Post extends Model
{
    use HasFiles;
}
```

---

## Query Methods

### files()

Get files query builder.

```php
public function files(?string $collection = null): MorphMany
```

**Parameters:**
- `$collection` (string|null): Filter by collection name

**Returns:** `MorphMany` - Query builder for files

**Examples:**

```php
// Get all files
$allFiles = $post->files()->get();

// Get files from specific collection
$images = $post->files('gallery')->get();

// Count files
$count = $post->files()->count();

// Order by size
$largest = $post->files()->orderBy('size', 'desc')->first();

// Eager load
$posts = Post::with('files')->get();
```

### file()

Get the first file from a collection.

```php
public function file(string $collection): ?File
```

**Parameters:**
- `$collection` (string): Collection name

**Returns:** `File|null` - First file or null if none exist

**Examples:**

```php
// Get single file
$avatar = $user->file('avatar');

// Check if exists
if ($thumbnail = $post->file('thumbnail')) {
    echo $thumbnail->url();
}

// Single file collections
$logo = $company->file('logo');
```

---

## Upload Methods

### attach()

Attach a file to the model.

```php
public function attach(
    string $collection,
    UploadedFile|string $file,
    ?array $metadata = null
): File
```

**Parameters:**
- `$collection` (string): Collection name
- `$file` (UploadedFile|string): Uploaded file or path
- `$metadata` (array|null): Optional custom metadata

**Returns:** `File` - Created file model

**Examples:**

```php
// Basic upload
$file = $post->attach('images', $request->file('photo'));

// With metadata
$file = $post->attach('documents', $uploadedFile, [
    'category' => 'invoice',
    'year' => 2024,
]);

// From path
$file = $user->attach('exports', '/tmp/export.csv');
```

**Throws:**
- `InvalidCollectionException` - If collection doesn't allow multiple files and one already exists
- `FileUploadException` - If upload fails

### attachMany()

Attach multiple files at once.

```php
public function attachMany(
    string $collection,
    array $files,
    ?array $metadata = null
): Collection
```

**Parameters:**
- `$collection` (string): Collection name
- `$files` (array): Array of UploadedFile instances
- `$metadata` (array|null): Optional metadata applied to all files

**Returns:** `Collection<File>` - Collection of created files

**Examples:**

```php
// Upload multiple files
$files = $post->attachMany('gallery', $request->file('images'));

// With metadata
$files = $product->attachMany('photos', $uploads, [
    'uploaded_by' => auth()->id(),
]);

// Iterate results
foreach ($files as $file) {
    echo $file->url();
}
```

**Throws:**
- `InvalidCollectionException` - If collection doesn't allow multiple files

### replace()

Replace an existing file in a collection.

```php
public function replace(
    string $collection,
    UploadedFile|string $file,
    ?int $fileId = null
): File
```

**Parameters:**
- `$collection` (string): Collection name
- `$file` (UploadedFile|string): New file
- `$fileId` (int|null): Specific file ID to replace (optional)

**Returns:** `File` - New file model

**Behavior:**
- Deletes old file(s) from storage
- Creates new file record
- For single-file collections, replaces the one file
- For multi-file collections, specify `$fileId` or replaces first file

**Examples:**

```php
// Replace in single-file collection
$newAvatar = $user->replace('avatar', $newImage);

// Replace specific file in multi-file collection
$newPhoto = $post->replace('gallery', $newImage, $oldFileId);

// Replace first file if no ID given
$replaced = $model->replace('documents', $newDoc);
```

---

## Deletion Methods

### detach()

Remove a file from the model.

```php
public function detach(string $collection, int $fileId): bool
```

**Parameters:**
- `$collection` (string): Collection name
- `$fileId` (int): File ID to remove

**Returns:** `bool` - True if deleted, false otherwise

**Behavior:**
- Deletes file from storage
- Removes database record
- Dispatches `FileDeleted` event

**Examples:**

```php
// Remove specific file
$post->detach('gallery', $fileId);

// Remove in loop
foreach ($filesToRemove as $fileId) {
    $post->detach('documents', $fileId);
}

// With confirmation
if ($user->detach('avatar', $avatarId)) {
    return response()->json(['success' => true]);
}
```

### detachAll()

Remove all files from a collection.

```php
public function detachAll(string $collection): int
```

**Parameters:**
- `$collection` (string): Collection name

**Returns:** `int` - Number of files deleted

**Examples:**

```php
// Clear entire collection
$count = $post->detachAll('gallery');

// Clear all attachments
$user->detachAll('documents');
```

---

## Configuration Methods

### getFileCollectionConfig()

Get configuration for a specific collection.

```php
public function getFileCollectionConfig(string $collection): array
```

**Parameters:**
- `$collection` (string): Collection name

**Returns:** `array` - Collection configuration

**Default config:**
```php
[
    'multiple' => true,
    'max_file_size' => 10240, // KB
    'allowed_mimes' => [],
]
```

**Examples:**

```php
// Get config
$config = $post->getFileCollectionConfig('gallery');

// Check if multiple files allowed
if ($config['multiple']) {
    // Allow multiple uploads
}

// Get max file size
$maxSize = $config['max_file_size'];
```

### Define Collection Config

Override in your model:

```php
class Post extends Model
{
    use HasFiles;

    protected array $fileCollections = [
        'thumbnail' => [
            'multiple' => false,
            'max_file_size' => 2048,
            'allowed_mimes' => ['image/jpeg', 'image/png'],
        ],
        'gallery' => [
            'multiple' => true,
            'max_file_size' => 5120,
            'allowed_mimes' => ['image/*'],
        ],
    ];
}
```

---

## Helper Methods

### hasFiles()

Check if model has any files.

```php
public function hasFiles(?string $collection = null): bool
```

**Parameters:**
- `$collection` (string|null): Check specific collection

**Returns:** `bool`

**Examples:**

```php
// Check any files
if ($post->hasFiles()) {
    // Has at least one file
}

// Check specific collection
if ($user->hasFiles('avatar')) {
    // Has avatar
}
```

### countFiles()

Count files in a collection.

```php
public function countFiles(?string $collection = null): int
```

**Parameters:**
- `$collection` (string|null): Count specific collection or all files

**Returns:** `int` - File count

**Examples:**

```php
// Count all files
$total = $post->countFiles();

// Count in collection
$imageCount = $post->countFiles('gallery');

// Display count
echo "{$post->countFiles()} files attached";
```

### getFilesByCollection()

Get files grouped by collection.

```php
public function getFilesByCollection(): Collection
```

**Returns:** `Collection` - Files grouped by collection name

**Examples:**

```php
$grouped = $post->getFilesByCollection();

foreach ($grouped as $collection => $files) {
    echo "Collection: {$collection} ({$files->count()} files)";
    foreach ($files as $file) {
        echo $file->original_name;
    }
}
```

---

## Relationship Methods

### fileableFiles()

Underlying morphMany relationship.

```php
public function fileableFiles(): MorphMany
```

**Returns:** `MorphMany`

**Note:** You typically use `files()` instead. This method exists for advanced use cases.

---

## Scopes

### whereHasFile()

Query models that have files in a specific collection.

```php
Post::whereHasFile('thumbnail')->get();
```

### whereDoesntHaveFile()

Query models that don't have files in a collection.

```php
User::whereDoesntHaveFile('avatar')->get();
```

---

## Events

Methods dispatch these events:

- `FileUploading` - Before upload
- `FileUploaded` - After successful upload
- `FileDeleting` - Before delete
- `FileDeleted` - After delete

See [Events](/advanced/events) for details.

---

## Examples

### Complete Upload Flow

```php
public function store(Request $request, Post $post)
{
    $request->validate([
        'image' => 'required|image|max:2048',
    ]);

    $file = $post->attach('images', $request->file('image'));

    return response()->json([
        'id' => $file->id,
        'url' => $file->url(),
        'size' => $file->human_readable_size,
    ]);
}
```

### Display Files in Blade

```blade
@if($post->hasFiles('gallery'))
    <div class="gallery">
        @foreach($post->files('gallery')->get() as $file)
            <img src="{{ $file->url() }}" alt="{{ $file->original_name }}">
        @endforeach
    </div>
@endif
```

### Batch Processing

```php
// Upload multiple files
$files = $request->file('attachments');
$uploaded = $post->attachMany('documents', $files);

// Set expiration on all
foreach ($uploaded as $file) {
    $file->expires_at = now()->addDays(30);
    $file->save();
}
```
