# Basic Operations

Learn the fundamental operations for working with file attachments in Filexus.

## Attaching Files

### Single File

Attach a file to a collection:

```php
$file = $model->attach('collection_name', $uploadedFile);
```

**Example:**

```php
$post = Post::find(1);
$file = $post->attach('thumbnail', $request->file('image'));

// Access file properties
echo $file->id;
echo $file->original_name;
echo $file->url();
```

### Multiple Files

Attach multiple files at once:

```php
$files = $model->attachMany('collection_name', $uploadedFiles);
```

**Example:**

```php
$files = $post->attachMany('gallery', $request->file('images'));

foreach ($files as $file) {
    echo $file->original_name . '<br>';
}
```

### With Progress Tracking

```php
$uploadedFiles = $request->file('images');
$totalFiles = count($uploadedFiles);
$uploaded = 0;

foreach ($uploadedFiles as $uploadedFile) {
    $file = $post->attach('gallery', $uploadedFile);
    $uploaded++;

    // Emit progress event, update UI, etc.
    event(new FileUploadProgress($uploaded, $totalFiles));
}
```

## Retrieving Files

### Get Single File

Get the first file from a collection:

```php
$file = $model->file('collection_name');
```

**Example:**

```php
$thumbnail = $post->file('thumbnail');

if ($thumbnail) {
    echo '<img src="' . $thumbnail->url() . '">';
}
```

### Get All Files from Collection

```php
$files = $model->files('collection_name')->get();
```

**Example:**

```php
$gallery = $post->files('gallery')->get();

foreach ($gallery as $image) {
    echo '<img src="' . $image->url() . '">';
}
```

### Get All Files

Get all files attached to a model regardless of collection:

```php
$allFiles = $model->files()->get();
```

**Example:**

```php
$allFiles = $post->files()->get();

foreach ($allFiles as $file) {
    echo $file->collection . ': ' . $file->original_name . '<br>';
}
```

### With Query Constraints

```php
// Get recent files
$recentFiles = $post->files('gallery')
    ->where('created_at', '>=', now()->subDays(7))
    ->get();

// Get large files
$largeFiles = $post->files()
    ->where('size', '>', 1024 * 1024) // Greater than 1MB
    ->get();

// Order by size
$filesBySize = $post->files('documents')
    ->orderBy('size', 'desc')
    ->get();
```

## Checking for Files

### Check if Model Has Any Files

```php
if ($model->hasFiles()) {
    // Model has at least one attached file
}
```

### Check Specific Collection

```php
if ($model->hasFile('gallery')) {
    // Model has files in the 'gallery' collection
}
```

### Count Files

```php
$count = $model->files('gallery')->count();

if ($count > 0) {
    echo "This post has {$count} gallery images.";
}
```

## Replacing Files

Replace an existing file in a collection:

```php
$newFile = $model->replace('collection_name', $uploadedFile);
```

**Example:**

```php
// Replace user avatar
$newAvatar = $user->replace('avatar', $request->file('new_avatar'));
// Old avatar is automatically deleted
```

**For Single-File Collections:**

```php
// If 'avatar' is configured as single-file collection
$user->replace('avatar', $newFile); // ✅ Correct

// This would throw InvalidCollectionException:
$user->attach('avatar', $newFile); // ❌ Error if file already exists
```

## Detaching Files

### Detach Specific File

Remove a file by its ID:

```php
$model->detach('collection_name', $fileId);
```

**Example:**

```php
$post->detach('gallery', $file->id);
// File is deleted from storage and database
```

### Detach All Files from Collection

```php
$model->detachAll('collection_name');
```

**Example:**

```php
$post->detachAll('gallery');
// All gallery images are deleted
```

### Detach All Files

Detach all files from all collections:

```php
$model->files()->each(function ($file) {
    $file->delete();
});
```

## File URLs

### Get Public URL

```php
$url = $file->url();
```

**Example:**

```php
<img src="{{ $post->file('thumbnail')->url() }}">
```

### Temporary URLs

For private disks (like S3 private), generate temporary signed URLs:

```php
$temporaryUrl = $file->temporaryUrl(now()->addHours(1));
```

**Example:**

```php
// Generate a URL valid for 24 hours
$downloadLink = $file->temporaryUrl(now()->addDay());

echo '<a href="' . $downloadLink . '">Download (valid for 24 hours)</a>';
```

## File Information

### Basic Properties

```php
$file->id;               // File ID
$file->original_name;    // "photo.jpg"
$file->mime;             // "image/jpeg"
$file->extension;        // "jpg"
$file->size;             // Size in bytes
$file->collection;       // "gallery"
$file->disk;             // "public"
$file->path;             // Storage path
$file->hash;             // SHA256 hash
```

### Human-Readable Size

```php
echo $file->human_readable_size;
// "1.5 MB", "250.0 KB", "3.2 GB"
```

### File Type Checks

```php
if ($file->isImage()) {
    echo '<img src="' . $file->url() . '">';
}

if ($file->isVideo()) {
    echo '<video src="' . $file->url() . '" controls></video>';
}

if ($file->isAudio()) {
    echo '<audio src="' . $file->url() . '" controls></audio>';
}

if ($file->isPdf()) {
    echo '<embed src="' . $file->url() . '" type="application/pdf">';
}
```

### Check if File Exists

```php
if ($file->exists()) {
    // File exists in storage
} else {
    // File record exists but physical file is missing
}
```

## Complete Controller Example

```php
namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostFileController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $request->validate([
            'collection' => 'required|string',
            'file' => 'required|file|max:10240',
        ]);

        $file = $post->attach(
            $request->collection,
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'file' => [
                'id' => $file->id,
                'name' => $file->original_name,
                'url' => $file->url(),
                'size' => $file->human_readable_size,
            ],
        ]);
    }

    public function destroy(Post $post, $fileId)
    {
        $file = $post->files()->findOrFail($fileId);
        $collection = $file->collection;

        $post->detach($collection, $fileId);

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully',
        ]);
    }

    public function replace(Request $request, Post $post, $collection)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $post->replace($collection, $request->file('file'));

        return response()->json([
            'success' => true,
            'file' => [
                'id' => $file->id,
                'url' => $file->url(),
            ],
        ]);
    }
}
```

## Error Handling

```php
use Filexus\Exceptions\FileUploadException;
use Filexus\Exceptions\InvalidCollectionException;

try {
    $file = $post->attach('thumbnail', $uploadedFile);
} catch (FileUploadException $e) {
    // File too large, invalid MIME type, or storage error
    return back()->withErrors(['file' => $e->getMessage()]);
} catch (InvalidCollectionException $e) {
    // Invalid collection configuration
    return back()->withErrors(['collection' => $e->getMessage()]);
}
```

## Blade Examples

### Display Single Image

```blade
@if($post->file('thumbnail'))
    <img src="{{ $post->file('thumbnail')->url() }}"
         alt="{{ $post->file('thumbnail')->original_name }}"
         class="w-full">
@endif
```

### Display Gallery

```blade
<div class="grid grid-cols-3 gap-4">
    @foreach($post->files('gallery')->get() as $image)
        <div class="relative">
            <img src="{{ $image->url() }}"
                 alt="{{ $image->original_name }}"
                 class="w-full h-48 object-cover">
            <span class="text-xs text-gray-500">
                {{ $image->human_readable_size }}
            </span>
        </div>
    @endforeach
</div>
```

### File List

```blade
<ul>
    @foreach($post->files('documents')->get() as $document)
        <li>
            <a href="{{ $document->url() }}"
               class="text-blue-600 hover:underline">
                {{ $document->original_name }}
            </a>
            <span class="text-sm text-gray-500">
                ({{ $document->human_readable_size }})
            </span>
        </li>
    @endforeach
</ul>
```
