# API Reference: File Model

Complete reference for the `File` model and its properties and methods.

## File Model

Represents a file attached to a model.

**Namespace:** `Filexus\Models\File`

**Table:** `files`

---

## Properties

### id

```php
public int $id
```

Primary key. Auto-increment by default, or UUID/ULID if configured.

### disk

```php
public string $disk
```

Storage disk name (e.g., `'public'`, `'s3'`, `'local'`).

### path

```php
public string $path
```

Relative path to file on disk (e.g., `'posts/123/images/abc123.jpg'`).

### collection

```php
public string $collection
```

Collection name (e.g., `'avatar'`, `'documents'`, `'gallery'`).

### fileable_type

```php
public string $fileable_type
```

Morph type - fully qualified class name of parent model (e.g., `'App\\Models\\Post'`).

### fileable_id

```php
public int|string $fileable_id
```

ID of parent model (int or UUID/ULID).

### original_name

```php
public string $original_name
```

Original filename from upload (e.g., `'my-photo.jpg'`).

### mime

```php
public string $mime
```

MIME type (e.g., `'image/jpeg'`, `'application/pdf'`).

### extension

```php
public string $extension
```

File extension without dot (e.g., `'jpg'`, `'pdf'`, `'docx'`).

### size

```php
public int $size
```

File size in bytes.

### hash

```php
public string $hash
```

SHA-256 hash of file content for integrity verification and deduplication.

### metadata

```php
public ?array $metadata
```

Custom JSON metadata. Cast to array automatically.

**Example:**
```php
$file->metadata = [
    'category' => 'invoice',
    'year' => 2024,
    'department' => 'finance',
];
```

### expires_at

```php
public ?Carbon $expires_at
```

Expiration timestamp. Cast to Carbon automatically. Null means no expiration.

### created_at

```php
public Carbon $created_at
```

When file was created.

### updated_at

```php
public Carbon $updated_at
```

When file was last updated.

---

## Relationships

### fileable()

Get the parent model.

```php
public function fileable(): MorphTo
```

**Returns:** `MorphTo` - Polymorphic relationship to parent

**Examples:**

```php
// Get parent model
$post = $file->fileable;

// Check parent type
if ($file->fileable instanceof User) {
    // File belongs to a user
}

// Eager load
$files = File::with('fileable')->get();
```

---

## Helper Methods

### url()

Get the public URL for the file.

```php
public function url(): string
```

**Returns:** `string` - Public URL

**Examples:**

```php
// Get URL
$url = $file->url();

// In Blade
<img src="{{ $file->url() }}" alt="{{ $file->original_name }}">

// Download link
<a href="{{ $file->url() }}" download>Download</a>
```

### thumbnailUrls()

Get URLs for all thumbnails (if they exist).

```php
public function thumbnailUrls(): array
```

**Returns:** `array<string, string>` - Array of size name => URL

**Examples:**

```php
// Get all thumbnail URLs
$thumbnails = $file->thumbnailUrls();
// [
//     'small' => 'https://example.com/storage/path/thumbnails/image_small.jpg',
//     'medium' => 'https://example.com/storage/path/thumbnails/image_medium.jpg',
//     'large' => 'https://example.com/storage/path/thumbnails/image_large.jpg',
// ]

// Loop through thumbnails
foreach ($file->thumbnailUrls() as $size => $url) {
    echo "<img src='{$url}' alt='{$size}'>";
}

// In Blade
@foreach($file->thumbnailUrls() as $size => $url)
    <img src="{{ $url }}" alt="{{ $size }}" class="thumb-{{ $size }}">
@endforeach
```

**Note:** Returns empty array if thumbnails don't exist or thumbnail generation is disabled.

### thumbnailUrl()

Get URL for a specific thumbnail size.

```php
public function thumbnailUrl(string $size): ?string
```

**Parameters:**
- `$size` - Thumbnail size name (e.g., 'small', 'medium', 'large')

**Returns:** `string|null` - URL or null if thumbnail doesn't exist

**Examples:**

```php
// Get specific thumbnail
$smallUrl = $file->thumbnailUrl('small');
$mediumUrl = $file->thumbnailUrl('medium');

// With null check
if ($url = $file->thumbnailUrl('large')) {
    echo "<img src='{$url}'>";
}

// With fallback
$url = $file->thumbnailUrl('medium') ?? $file->url();

// In Blade
<img src="{{ $file->thumbnailUrl('medium') ?? $file->url() }}" alt="{{ $file->original_name }}">
```

### hasThumbnails()

Check if file has any thumbnails.

```php
public function hasThumbnails(): bool
```

**Returns:** `bool` - True if thumbnails exist

**Examples:**

```php
// Check before displaying
if ($file->hasThumbnails()) {
    echo '<picture>';
    foreach ($file->thumbnailUrls() as $size => $url) {
        echo "<source srcset='{$url}' media='...'>  ";
    }
    echo '</picture>';
}

// In Blade
@if($file->hasThumbnails())
    <picture>
        <source srcset="{{ $file->thumbnailUrl('large') }}" media="(min-width: 1024px)">
        <source srcset="{{ $file->thumbnailUrl('medium') }}" media="(min-width: 768px)">
        <img src="{{ $file->thumbnailUrl('small') }}" alt="{{ $file->original_name }}">
    </picture>
@else
    <img src="{{ $file->url() }}" alt="{{ $file->original_name }}">
@endif
```

**See Also:** [Image Thumbnails](/advanced/thumbnails)

### path()

Get the full storage path.

```php
public function path(): string
```

**Returns:** `string` - Absolute path on disk

**Examples:**

```php
// Get full path
$fullPath = $file->path();

// Read file content
$content = file_get_contents($file->path());

// Process file
Image::make($file->path())->resize(300, 300)->save();
```

### download()

Get a download response.

```php
public function download(?string $name = null): StreamedResponse
```

**Parameters:**
- `$name` (string|null): Custom filename for download

**Returns:** `StreamedResponse` - Laravel download response

**Examples:**

```php
// Download with original name
return $file->download();

// Download with custom name
return $file->download('custom-name.pdf');

// In controller
public function download(File $file)
{
    return $file->download();
}
```

### human_readable_size

Get formatted file size.

```php
public function getHumanReadableSizeAttribute(): string
```

**Returns:** `string` - Formatted size (e.g., "1.5 MB", "256 KB")

**Examples:**

```php
// Display size
echo $file->human_readable_size; // "2.4 MB"

// In Blade
<span>{{ $file->human_readable_size }}</span>
```

---

## Type Check Methods

### isImage()

Check if file is an image.

```php
public function isImage(): bool
```

**Returns:** `bool`

Checks for: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`

**Examples:**

```php
if ($file->isImage()) {
    // Show image preview
    echo "<img src='{$file->url()}'>";
}
```

### isVideo()

Check if file is a video.

```php
public function isVideo(): bool
```

**Returns:** `bool`

Checks for: `video/mp4`, `video/mpeg`, `video/quicktime`, `video/x-msvideo`, `video/webm`

**Examples:**

```php
if ($file->isVideo()) {
    // Show video player
    echo "<video src='{$file->url()}' controls></video>";
}
```

### isAudio()

Check if file is audio.

```php
public function isAudio(): bool
```

**Returns:** `bool`

Checks for: `audio/mpeg`, `audio/ogg`, `audio/wav`, `audio/webm`

### isPdf()

Check if file is a PDF.

```php
public function isPdf(): bool
```

**Returns:** `bool`

Checks for: `application/pdf`

### isDocument()

Check if file is a document.

```php
public function isDocument(): bool
```

**Returns:** `bool`

Checks for Word, Excel, PowerPoint, and text documents.

### isArchive()

Check if file is an archive.

```php
public function isArchive(): bool
```

**Returns:** `bool`

Checks for: `application/zip`, `application/x-rar-compressed`, `application/x-7z-compressed`

---

## Expiration Methods

### isExpired()

Check if file has expired.

```php
public function isExpired(): bool
```

**Returns:** `bool`

**Examples:**

```php
if ($file->isExpired()) {
    return response()->json(['error' => 'File has expired'], 410);
}
```

### isNotExpired()

Check if file has not expired.

```php
public function isNotExpired(): bool
```

**Returns:** `bool`

---

## Deduplication Methods

### reference_count

Get the number of file records that share the same hash.

```php
public function getReferenceCountAttribute(): int
```

**Returns:** `int` - Number of File records with the same hash

**Usage:**

```php
$count = $file->reference_count;
```

**Examples:**

```php
// Check how many times this file content is referenced
$file = File::find(1);
echo "This file is referenced {$file->reference_count} times";

// When deduplication is enabled
config(['filexus.deduplicate' => true]);

$file1 = $post1->attach('image', $uploadedFile1);
$file2 = $post2->attach('image', $uploadedFile2); // Same content

echo $file1->reference_count; // 2
echo $file2->reference_count; // 2
```

**Use Cases:**
- Check if deleting this record would delete the physical file
- Determine deduplication savings
- Show storage optimization metrics

### isLastReference()

Check if this is the last File record referencing this physical file.

```php
public function isLastReference(): bool
```

**Returns:** `bool` - True if only one File record has this hash

**Examples:**

```php
// Check before deleting
if ($file->isLastReference()) {
    // This deletion will remove the physical file
    Log::info("Deleting last reference to {$file->path}");
} else {
    // Physical file will remain (other records reference it)
    Log::info("Deleting reference, physical file preserved");
}

$file->delete();

// Safe deletion check
if (!$file->isLastReference()) {
    // Safe to delete - other records still reference this file
    $file->delete();
}

// In a cleanup command
foreach (File::whereExpired()->get() as $file) {
    if ($file->isLastReference()) {
        echo "Will delete physical file: {$file->path}";
    }
    $file->delete();
}
```

**Relationship to Deduplication:**

When deduplication is enabled, multiple File records may share the same physical file:

```php
config(['filexus.deduplicate' => true]);

// Upload same file twice
$file1 = $user1->attach('avatar', $photo);
$file2 = $user2->attach('avatar', $photo); // Same content

$file1->isLastReference(); // false (2 references)
$file2->isLastReference(); // false (2 references)
$file1->reference_count;   // 2
$file2->reference_count;   // 2

// Delete first one
$file1->delete();

// Now file2 is the last reference
$file2->isLastReference(); // true (1 reference)
$file2->reference_count;   // 1
```

**Note:** The File model's `deleting` event automatically handles reference counting to prevent premature deletion of physical files when deduplication is enabled.

---

## Query Scopes

### whereCollection()

Filter by collection name.

```php
File::whereCollection('gallery')->get();
```

### whereExpired()

Get expired files.

```php
File::whereExpired()->get();
```

### whereNotExpired()

Get non-expired files.

```php
File::whereNotExpired()->get();
```

### whereOrphaned()

Get orphaned files (fileable no longer exists).

```php
File::whereOrphaned()->get();
```

### whereMime()

Filter by MIME type.

```php
File::whereMime('image/jpeg')->get();
```

### whereExtension()

Filter by extension.

```php
File::whereExtension('pdf')->get();
```

---

## Mass Assignment

Files should generally not be created directly. Use the `HasFiles` trait methods instead.

If needed, mass assignable attributes:
- `disk`
- `path`
- `collection`
- `original_name`
- `mime`
- `extension`
- `size`
- `hash`
- `metadata`
- `expires_at`

---

## Deletion

When a `File` is deleted:
1. Physical file is removed from storage
2. Database record is deleted
3. `FileDeleted` event is dispatched

```php
$file = File::find($id);
$file->delete(); // Deletes from storage AND database
```

---

## JSON Serialization

File model includes these attributes when serialized to JSON:

```php
$file->toArray();
```

```json
{
  "id": 1,
  "disk": "public",
  "path": "posts/123/images/abc123.jpg",
  "collection": "gallery",
  "fileable_type": "App\\Models\\Post",
  "fileable_id": 123,
  "original_name": "my-photo.jpg",
  "mime": "image/jpeg",
  "extension": "jpg",
  "size": 2048576,
  "hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "metadata": {"category": "featured"},
  "expires_at": "2024-12-31T23:59:59.000000Z",
  "created_at": "2024-01-15T10:30:00.000000Z",
  "updated_at": "2024-01-15T10:30:00.000000Z",
  "url": "https://example.com/storage/posts/123/images/abc123.jpg"
}
```

Hidden by default: None

Appended attributes:
- `url`
- `human_readable_size`

---

## Examples

### Display File Info

```blade
<div class="file-info">
    <h3>{{ $file->original_name }}</h3>
    <ul>
        <li>Size: {{ $file->human_readable_size }}</li>
        <li>Type: {{ $file->mime }}</li>
        <li>Collection: {{ $file->collection }}</li>
        <li>Uploaded: {{ $file->created_at->diffForHumans() }}</li>
        @if($file->expires_at)
            <li>Expires: {{ $file->expires_at->diffForHumans() }}</li>
        @endif
    </ul>
    <a href="{{ $file->url() }}" class="btn">View</a>
    <a href="{{ route('files.download', $file) }}" class="btn">Download</a>
</div>
```

### File Gallery

```blade
<div class="gallery">
    @foreach($post->files('gallery')->get() as $file)
        @if($file->isImage())
            <img src="{{ $file->url() }}" alt="{{ $file->original_name }}">
        @elseif($file->isVideo())
            <video src="{{ $file->url() }}" controls></video>
        @else
            <a href="{{ $file->download() }}">
                {{ $file->original_name }} ({{ $file->human_readable_size }})
            </a>
        @endif
    @endforeach
</div>
```

### API Resource

```php
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'url' => $this->url(),
            'size' => $this->size,
            'formatted_size' => $this->human_readable_size,
            'type' => $this->mime,
            'extension' => $this->extension,
            'is_image' => $this->isImage(),
            'is_video' => $this->isVideo(),
            'collection' => $this->collection,
            'uploaded_at' => $this->created_at->toISOString(),
            'metadata' => $this->metadata,
        ];
    }
}
```
