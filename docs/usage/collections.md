# Collections

Collections allow you to organize files into logical groups. Each model can have multiple collections with different configurations.

## What are Collections?

Collections are named groups of files. For example:

- A `User` might have `avatar` and `documents` collections
- A `Post` might have `thumbnail` and `gallery` collections
- A `Product` might have `featured_image`, `gallery`, and `manuals` collections

## Defining Collections

Collections are defined in your model's `$fileCollections` property:

```php
use Filexus\Traits\HasFiles;

class Post extends Model
{
    use HasFiles;

    protected $fileCollections = [
        'thumbnail' => [
            'multiple' => false,
        ],
        'gallery' => [
            'multiple' => true,
        ],
    ];
}
```

## Single vs Multiple Files

### Single-File Collections

Configured with `'multiple' => false`:

```php
'avatar' => [
    'multiple' => false,
],
```

**Behavior:**
- Only one file allowed
- New attachments throw `InvalidCollectionException`
- Use `replace()` instead of `attach()` for updates

**Example:**

```php
// First upload
$user->attach('avatar', $avatarFile); // ✅ Works

// Second upload
$user->attach('avatar', $newFile); // ❌ Throws exception

// Correct way to update
$user->replace('avatar', $newFile); // ✅ Works, old file deleted
```

### Multi-File Collections

Configured with `'multiple' => true`:

```php
'gallery' => [
    'multiple' => true,
],
```

**Behavior:**
- Multiple files allowed
- Can add files with `attach()` or `attachMany()`
- Each file maintains its own record

**Example:**

```php
$post->attach('gallery', $image1); // ✅
$post->attach('gallery', $image2); // ✅
$post->attachMany('gallery', [$image3, $image4]); // ✅

echo $post->files('gallery')->count(); // 4
```

## Validating Collections

### File Size Limits

```php
'avatar' => [
    'max_file_size' => 2048, // 2MB in KB
],
'videos' => [
    'max_file_size' => 102400, // 100MB in KB
],
```

### MIME Type Restrictions

```php
'avatar' => [
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
    ],
],
'documents' => [
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
    ],
],
```

### Complete Example

```php
protected $fileCollections = [
    'avatar' => [
        'multiple' => false,
        'max_file_size' => 2048,
        'allowed_mimes' => ['image/jpeg', 'image/png'],
    ],
    'gallery' => [
        'multiple' => true,
        'max_file_size' => 5120,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
    'documents' => [
        'multiple' => true,
        'max_file_size' => 10240,
        'allowed_mimes' => ['application/pdf'],
    ],
];
```

## Working with Collections

### Attach to Collection

```php
$file = $model->attach('collection_name', $uploadedFile);
```

### Get Files from Collection

```php
// Get single file
$thumbnail = $post->file('thumbnail');

// Get all files from collection
$gallery = $post->files('gallery')->get();

// Get with constraints
$recentGallery = $post->files('gallery')
    ->where('created_at', '>=', now()->subDays(7))
    ->get();
```

### Check Collection

```php
if ($post->hasFile('thumbnail')) {
    // Has thumbnail
}

$count = $post->files('gallery')->count();
```

### Remove from Collection

```php
// Remove specific file
$post->detach('gallery', $fileId);

// Remove all files from collection
$post->detachAll('gallery');
```

## Dynamic Collections

Create collections dynamically based on user role or other criteria:

```php
class Post extends Model
{
    use HasFiles;

    public function getFileCollectionsAttribute(): array
    {
        $collections = [
            'thumbnail' => [
                'multiple' => false,
                'max_file_size' => 2048,
            ],
        ];

        // Admins can upload videos
        if (auth()->user()?->isAdmin()) {
            $collections['videos'] = [
                'multiple' => true,
                'max_file_size' => 102400,
                'allowed_mimes' => ['video/mp4'],
            ];
        }

        return $collections;
    }
}
```

## Collection Naming

**Best Practices:**
- Use descriptive names: `avatar`, `gallery`, `documents`
- Use snake_case: `profile_picture`, `legal_documents`
- Be consistent across models
- Avoid generic names like `files` or `attachments` (too vague)

**Good Names:**
```php
'avatar'
'banner'
'profile_picture'
'cover_photo'
'gallery'
'product_images'
'legal_documents'
'financial_statements'
'user_uploads'
```

**Avoid:**
```php
'file'      // Too generic
'img'       // Too abbreviated
'stuff'     // Not descriptive
'uploads'   // What kind of uploads?
```

## Blade Examples

### Display Collection

```blade
{{-- Single file collection --}}
@if($post->file('thumbnail'))
    <img src="{{ $post->file('thumbnail')->url() }}" class="w-full">
@endif

{{-- Multiple file collection --}}
<div class="gallery">
    @forelse($post->files('gallery')->get() as $image)
        <img src="{{ $image->url() }}" alt="{{ $image->original_name }}">
    @empty
        <p>No gallery images</p>
    @endforelse
</div>
```

### Upload Forms

```blade
{{-- Single file --}}
<form action="{{ route('posts.upload') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="collection" value="thumbnail">
    <input type="file" name="file" accept="image/*" required>
    <button type="submit">Upload Thumbnail</button>
</form>

{{-- Multiple files --}}
<form action="{{ route('posts.upload') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="collection" value="gallery">
    <input type="file" name="files[]" accept="image/*" multiple>
    <button type="submit">Upload Gallery</button>
</form>
```

## API Resource Example

```php
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
            'documents' => $this->files('documents')->get()->map(fn($file) => [
                'id' => $file->id,
                'name' => $file->original_name,
                'url' => $file->url(),
                'type' => $file->mime,
            ]),
        ];
    }
}
```

## Collection Strategies

### User-Facing Assets

```php
'avatar' => ['multiple' => false, 'max_file_size' => 2048],
'cover_photo' => ['multiple' => false, 'max_file_size' => 5120],
'banner' => ['multiple' => false, 'max_file_size' => 3072],
```

### Content Collections

```php
'featured_image' => ['multiple' => false],
'gallery' => ['multiple' => true],
'inline_images' => ['multiple' => true],
```

### Document Management

```php
'contract' => ['multiple' => false, 'allowed_mimes' => ['application/pdf']],
'invoices' => ['multiple' => true, 'allowed_mimes' => ['application/pdf']],
'receipts' => ['multiple' => true],
```

### Media Libraries

```php
'audio_files' => [
    'multiple' => true,
    'allowed_mimes' => ['audio/mpeg', 'audio/mp4'],
    'max_file_size' => 20480,
],
'video_files' => [
    'multiple' => true,
    'allowed_mimes' => ['video/mp4'],
    'max_file_size' => 102400,
],
```
