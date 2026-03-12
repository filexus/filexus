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

### fileFromLoaded()

Get the first file from a collection, using eager-loaded relationships to avoid N+1 queries.

```php
public function fileFromLoaded(string $collection): ?File
```

**Parameters:**
- `$collection` (string): Collection name

**Returns:** `File|null` - First file or null if none exist

**Behavior:**
- If the `files` relationship is already loaded, filters from the in-memory collection
- Otherwise, falls back to querying the database (same as `file()`)
- **Prevents N+1 queries** when used with eager loading

**⚠️ Important:** If you eager load with a collection constraint, only those collections are available. Accessing a different collection will return `null` without triggering a query:

```php
// Only loads 'avatar' collection
$users = User::with(['files' => fn($q) => $q->whereCollection('avatar')])->get();

$user = $users->first();
$avatar = $user->fileFromLoaded('avatar');     // ✅ Returns avatar (was loaded)
$cover = $user->fileFromLoaded('cover_photo'); // ⚠️ Returns null (wasn't loaded)
```

**Examples:**

```php
// ❌ N+1 Problem: Each call to file() triggers a query
$users = User::all();
foreach ($users as $user) {
    $avatar = $user->file('avatar'); // Query executed 100 times for 100 users!
}

// ✅ Solution: Eager load + fileFromLoaded()
$users = User::with(['files' => fn($q) => $q->whereCollection('avatar')])->get();
foreach ($users as $user) {
    $avatar = $user->fileFromLoaded('avatar'); // No additional queries!
}

// In API Resources
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->fileFromLoaded('avatar')?->url(),
        ];
    }
}

// In controller
$users = User::with(['files' => fn($q) => $q->whereCollection('avatar')])->get();
return UserResource::collection($users);
```

**When to Use:**
- When querying multiple models (collections of models)
- In API Resources that transform many models
- When you've already eager loaded the files relationship
- Any situation where you want to avoid N+1 query problems

### getFilesFromLoaded()

Get files from a collection, using eager-loaded relationships to avoid N+1 queries.

```php
public function getFilesFromLoaded(?string $collection = null): EloquentCollection
```

**Parameters:**
- `$collection` (string|null): Collection name (null returns all files)

**Returns:** `EloquentCollection<File>` - Collection of files

**Behavior:**
- If the `files` relationship is already loaded, filters from the in-memory collection
- Otherwise, falls back to querying the database (same as `getFiles()`)
- **Prevents N+1 queries** when used with eager loading

**⚠️ Important:** If you eager load with a collection constraint, only those collections are available. Accessing a different collection will return an empty collection without triggering a query:

```php
// Only loads 'gallery' collection
$posts = Post::with(['files' => fn($q) => $q->whereCollection('gallery')])->get();

$post = $posts->first();
$gallery = $post->getFilesFromLoaded('gallery');     // ✅ Returns gallery files (was loaded)
$documents = $post->getFilesFromLoaded('documents'); // ⚠️ Returns empty collection (wasn't loaded)
```

**Examples:**

```php
// ❌ N+1 Problem: Each call to getFiles() triggers a query
$posts = Post::all();
foreach ($posts as $post) {
    $images = $post->getFiles('gallery'); // Query for each post!
}

// ✅ Solution: Eager load + getFilesFromLoaded()
$posts = Post::with(['files' => fn($q) => $q->whereCollection('gallery')])->get();
foreach ($posts as $post) {
    $images = $post->getFilesFromLoaded('gallery'); // No additional queries!
}

// Get all files without filtering
$posts = Post::with('files')->get();
foreach ($posts as $post) {
    $allFiles = $post->getFilesFromLoaded(); // Returns all files
}

// In Blade views with eager loading
@foreach($products->load('files') as $product)
    @foreach($product->getFilesFromLoaded('images') as $image)
        <img src="{{ $image->url() }}" />
    @endforeach
@endforeach
```

**When to Use:**
- When querying multiple models with files
- In loops where you're accessing files for many models
- When you've already eager loaded the files relationship
- Any situation where you want to avoid N+1 query problems

---

## Upload Methods

### attach()

Attach a file to the model.

```php
public function attach(
    string $collection,
    UploadedFile $file,
    array $options = []
): File
```

**Parameters:**
- `$collection` (string): Collection name
- `$file` (UploadedFile): Uploaded file
- `$options` (array): Optional upload options

**Options:**
- `expires_at` (Carbon|string|null): Expiration date/time for the file

**Returns:** `File` - Created file model

**Throws:**
- `InvalidCollectionException` - If collection doesn't allow multiple files and one already exists

**Examples:**

```php
// Basic upload
$file = $post->attach('images', $request->file('photo'));

// With expiration
$file = $post->attach('temporary', $uploadedFile, [
    'expires_at' => now()->addDays(7)
]);

// Permanent file (no expiration)
$file = $post->attach('documents', $uploadedFile);

// With specific expiration time
$file = $user->attach('session_files', $tempFile, [
    'expires_at' => Carbon::parse('2026-12-31 23:59:59')
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
    array $options = []
): Collection
```

**Parameters:**
- `$collection` (string): Collection name
- `$files` (array): Array of UploadedFile instances
- `$options` (array): Optional upload options (same as `attach()`)

**Options:**
- `expires_at` (Carbon|string|null): Expiration date/time for all files

**Returns:** `Collection<File>` - Collection of created files

**Examples:**

```php
// Upload multiple files
$files = $post->attachMany('gallery', $request->file('images'));

// With expiration (applied to all files)
$files = $user->attachMany('temp_uploads', $uploads, [
    'expires_at' => now()->addHours(24),
]);

// Iterate results
foreach ($files as $file) {
    echo $file->url();
    echo $file->expires_at; // All have same expiration
}
```

**Throws:**
- `InvalidCollectionException` - If collection doesn't allow multiple files
- `FileUploadException` - If any upload fails

### replace()

Replace an existing file in a collection.

```php
public function replace(
    string $collection,
    UploadedFile $file,
    array $options = []
): File
```

**Parameters:**
- `$collection` (string): Collection name
- `$file` (UploadedFile): New file
- `$options` (array): Optional upload options (same as `attach()`)

**Options:**
- `expires_at` (Carbon|string|null): Expiration date/time for the new file

**Returns:** `File` - New file model

**Behavior:**
- Deletes ALL old file(s) from the collection
- Deletes files from storage
- Creates new file record
- Works for both single-file and multi-file collections

**Examples:**

```php
// Replace in single-file collection
$newAvatar = $user->replace('avatar', $newImage);

// Replace all files in a collection
$newThumbnail = $post->replace('thumbnails', $newImage);

// Replace with expiration
$newFile = $post->replace('temporary', $uploadedFile, [
    'expires_at' => now()->addWeek()
]);
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
