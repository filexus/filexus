# Quick Start

Get up and running with Filexus in minutes. This guide covers the essential steps to attach files to your models.

## Step 1: Add the Trait

Add the `HasFiles` trait to any Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use Filexus\Traits\HasFiles;

class Post extends Model
{
    use HasFiles;
}
```

That's it! Your model can now attach files.

## Step 2: Attach Files

### Single File

Attach a file to a collection:

```php
$post = Post::find(1);
$file = $post->attach('thumbnail', $request->file('image'));

echo $file->original_name; // "photo.jpg"
echo $file->url();         // Full URL to the file
```

### Multiple Files

Attach multiple files at once:

```php
$files = $post->attachMany('gallery', $request->file('images'));

foreach ($files as $file) {
    echo $file->original_name;
}
```

## Step 3: Retrieve Files

### Get Single File

```php
// Get the first file from a collection
$thumbnail = $post->file('thumbnail');

if ($thumbnail) {
    echo $thumbnail->url();
}
```

### Get All Files from Collection

```php
// Get all gallery images
$gallery = $post->files('gallery')->get();

foreach ($gallery as $image) {
    echo $image->url();
}
```

### Get All Files

```php
// Get all files attached to the post
$allFiles = $post->files()->get();
```

## Step 4: Update Files

### Replace a File

Replace an existing file in a collection:

```php
$newFile = $post->replace('thumbnail', $request->file('new_image'));
// Old thumbnail is automatically deleted
```

### Detach a File

Remove a specific file:

```php
$post->detach('gallery', $fileId);
```

### Detach All Files from Collection

```php
$post->detachAll('gallery');
```

## Step 5: Display Files in Views

### Blade Template

```blade
{{-- Display single file --}}
@if($post->file('thumbnail'))
    <img src="{{ $post->file('thumbnail')->url() }}"
         alt="{{ $post->file('thumbnail')->original_name }}">
@endif

{{-- Display gallery --}}
<div class="gallery">
    @foreach($post->files('gallery')->get() as $image)
        <img src="{{ $image->url() }}"
             alt="{{ $image->original_name }}">
    @endforeach
</div>
```

## Complete Example

Here's a complete example using a controller:

```php
namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'thumbnail' => 'required|image|max:2048',
            'gallery.*' => 'image|max:5120',
        ]);

        $post = Post::create([
            'title' => $request->title,
        ]);

        // Attach thumbnail
        $post->attach('thumbnail', $request->file('thumbnail'));

        // Attach gallery images
        if ($request->hasFile('gallery')) {
            $post->attachMany('gallery', $request->file('gallery'));
        }

        return redirect()->route('posts.show', $post);
    }

    public function update(Request $request, Post $post)
    {
        $request->validate([
            'title' => 'required',
            'thumbnail' => 'image|max:2048',
        ]);

        $post->update([
            'title' => $request->title,
        ]);

        // Replace thumbnail if new one uploaded
        if ($request->hasFile('thumbnail')) {
            $post->replace('thumbnail', $request->file('thumbnail'));
        }

        return redirect()->route('posts.show', $post);
    }

    public function destroy(Post $post)
    {
        $post->delete(); // All files are automatically deleted

        return redirect()->route('posts.index');
    }
}
```

## What's Next?

- **Collections**: Learn about [configuring collections](/usage/collections)
- **Configuration**: Customize behavior with [global](/configuration/global) or [per-model](/configuration/per-model) configuration
- **Metadata**: Work with [file metadata](/usage/metadata) like size, MIME type, and more
- **Advanced**: Explore [events](/advanced/events), query scopes, and more

## Common Patterns

### Upload Form

```html
<form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <input type="text" name="title" required>

    <label>Thumbnail</label>
    <input type="file" name="thumbnail" accept="image/*" required>

    <label>Gallery Images</label>
    <input type="file" name="gallery[]" accept="image/*" multiple>

    <button type="submit">Create Post</button>
</form>
```

### API Resource

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'thumbnail' => $this->file('thumbnail')?->url(),
            'gallery' => $this->files('gallery')->get()->map(fn($file) => [
                'id' => $file->id,
                'url' => $file->url(),
                'name' => $file->original_name,
                'size' => $file->human_readable_size,
            ]),
        ];
    }
}
```

### Checking for Files

```php
// Check if model has any files
if ($post->hasFiles()) {
    // ...
}

// Check specific collection
if ($post->hasFile('gallery')) {
    // ...
}

// Count files in collection
$count = $post->files('gallery')->count();
```
