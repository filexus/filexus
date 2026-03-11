# File Metadata

Filexus automatically stores comprehensive metadata for each uploaded file.

## Available Metadata

Every `File` model includes these properties:

```php
$file->id;               // Primary key (int, uuid, or ulid)
$file->disk;             // Storage disk name
$file->path;             // Path on disk
$file->collection;       // Collection name
$file->fileable_type;    // Parent model class
$file->fileable_id;      // Parent model ID
$file->original_name;    // Original filename
$file->mime;             // MIME type
$file->extension;        // File extension
$file->size;             // Size in bytes
$file->hash;             // SHA256 hash
$file->metadata;         // Custom JSON metadata
$file->expires_at;       // Expiration timestamp
$file->created_at;       // Upload timestamp
$file->updated_at;       // Last update timestamp
```

## Basic Properties

### Original Name

The filename as uploaded by the user:

```php
$file = $post->attach('documents', $uploadedFile);
echo $file->original_name; // "Annual_Report_2024.pdf"
```

### MIME Type

The media type of the file:

```php
echo $file->mime; // "application/pdf"

if ($file->mime === 'image/jpeg') {
    // Handle JPEG
}
```

### Extension

The file extension:

```php
echo $file->extension; // "pdf"

$icon = match ($file->extension) {
    'pdf' => 'file-pdf',
    'doc', 'docx' => 'file-word',
    'xls', 'xlsx' => 'file-excel',
    default => 'file',
};
```

### File Size

Raw size in bytes:

```php
echo $file->size; // 1048576 (1MB in bytes)

// Human-readable format
echo $file->human_readable_size; // "1.0 MB"
```

### Storage Information

```php
echo $file->disk; // "public" or "s3"
echo $file->path; // "Post/123/gallery/uuid.jpg"
```

### File Hash

SHA256 hash for integrity checking and deduplication:

```php
echo $file->hash;
// "a3d4f5e6...full-sha256-hash"

// Check if another file has same content
$duplicate = File::where('hash', $file->hash)->first();
```

## Helper Methods

### Human-Readable Size

Automatically formatted:

```php
echo $file->human_readable_size;
// "1.5 MB", "250.0 KB", "3.2 GB", "128 Bytes"
```

### File Type Checks

```php
if ($file->isImage()) {
    // image/jpeg, image/png, image/gif, image/webp, etc.
}

if ($file->isVideo()) {
    // video/mp4, video/mpeg, etc.
}

if ($file->isAudio()) {
    // audio/mpeg, audio/mp4, etc.
}

if ($file->isPdf()) {
    // application/pdf
}
```

### Storage Check

```php
if ($file->exists()) {
    // File physically exists on disk
} else {
    // File record exists but physical file is missing
}
```

## Custom Metadata

Store additional data as JSON:

### Setting Metadata

```php
$file = $post->attach('photos', $uploadedFile);

$file->metadata = [
    'camera' => 'Canon EOS R5',
    'location' => 'San Francisco',
    'photographer' => 'John Doe',
    'copyright' => '© 2024 Company Name',
    'tags' => ['nature', 'landscape', 'sunset'],
];

$file->save();
```

### Reading Metadata

```php
$camera = $file->metadata['camera'] ?? 'Unknown';
$tags = $file->metadata['tags'] ?? [];

if (isset($file->metadata['location'])) {
    echo "Photo taken in: " . $file->metadata['location'];
}
```

### Querying by Metadata

```php
// Find files by metadata
$sunsetPhotos = File::whereJsonContains('metadata->tags', 'sunset')->get();

$canonPhotos = File::where('metadata->camera', 'Canon EOS R5')->get();
```

## Relationship Data

### Parent Model

Access the model that owns the file:

```php
$file = File::find(1);
$post = $file->fileable; // Returns the Post model

echo $file->fileable_type; // "App\\Models\\Post"
echo $file->fileable_id;   // 123
```

### Collection

Get the collection name:

```php
echo $file->collection; // "gallery"

// Query files by collection
$thumbnails = File::whereCollection('thumbnail')->get();
```

## Timestamps

### Created At

When the file was uploaded:

```php
echo $file->created_at->format('Y-m-d H:i:s');
echo $file->created_at->diffForHumans(); // "2 hours ago"

// Find recently uploaded files
$recent = File::where('created_at', '>=', now()->subHours(24))->get();
```

### Updated At

Last modification time:

```php
echo $file->updated_at->format('Y-m-d H:i:s');
```

## Display Examples

### File Card

```blade
<div class="file-card">
    <div class="file-icon">
        @if($file->isImage())
            <img src="{{ $file->url() }}" alt="{{ $file->original_name }}">
        @else
            <i class="icon-file-{{ $file->extension }}"></i>
        @endif
    </div>

    <div class="file-info">
        <h4>{{ $file->original_name }}</h4>
        <p class="text-sm text-gray-600">
            {{ $file->human_readable_size }} •
            {{ $file->created_at->format('M d, Y') }}
        </p>

        @if($file->metadata)
            <div class="metadata">
                @foreach($file->metadata as $key => $value)
                    <span class="badge">{{ $key }}: {{ $value }}</span>
                @endforeach
            </div>
        @endif
    </div>
</div>
```

### File List

```blade
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Size</th>
            <th>Uploaded</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($files as $file)
            <tr>
                <td>{{ $file->original_name }}</td>
                <td>{{ $file->extension }}</td>
                <td>{{ $file->human_readable_size }}</td>
                <td>{{ $file->created_at->format('M d, Y') }}</td>
                <td>
                    <a href="{{ $file->url() }}" class="btn">Download</a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
```

## API Resource

```php
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'type' => $this->mime,
            'extension' => $this->extension,
            'size' => $this->size,
            'size_human' => $this->human_readable_size,
            'url' => $this->url(),
            'collection' => $this->collection,
            'hash' => $this->hash,
            'metadata' => $this->metadata,
            'uploaded_at' => $this->created_at->toISOString(),
            'checks' => [
                'is_image' => $this->isImage(),
                'is_video' => $this->isVideo(),
                'is_audio' => $this->isAudio(),
                'is_pdf' => $this->isPdf(),
                'exists' => $this->exists(),
            ],
        ];
    }
}
```

## Advanced Queries

### Filter by Size

```php
// Files larger than 5MB
$largeFiles = File::where('size', '>', 5 * 1024 * 1024)->get();

// Files smaller than 100KB
$smallFiles = File::where('size', '<', 100 * 1024)->get();
```

### Filter by Type

```php
// All images
$images = File::where('mime', 'like', 'image/%')->get();

// All PDFs
$pdfs = File::where('mime', 'application/pdf')->get();

// Specific extensions
$spreadsheets = File::whereIn('extension', ['xls', 'xlsx', 'csv'])->get();
```

### Filter by Date

```php
// Files from last month
$lastMonth = File::whereBetween('created_at', [
    now()->subMonth(),
    now(),
])->get();

// Files from specific year
$year2024 = File::whereYear('created_at', 2024)->get();
```

## Validation Based on Metadata

```php
public function store(Request $request)
{
    $file = $post->attach('photo', $request->file('photo'));

    // Validate image dimensions if metadata available
    if ($file->isImage() && isset($file->metadata['width'])) {
        if ($file->metadata['width'] < 800) {
            $file->delete();
            return back()->withErrors([
                'photo' => 'Image must be at least 800px wide'
            ]);
        }
    }

    return back()->with('success', 'Photo uploaded');
}
```
