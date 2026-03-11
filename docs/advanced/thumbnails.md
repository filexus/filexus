# Image Thumbnails

Filexus includes automatic thumbnail generation for image uploads. When enabled, thumbnails are automatically created in various sizes for responsive images and better performance.

## Requirements

**Important**: Thumbnail generation requires the [Intervention Image](https://image.intervention.io/) package to be installed:

```bash
composer require intervention/image
```

The package supports both GD and Imagick drivers. Install at least one:

```bash
# GD (usually pre-installed with PHP)
php -m | grep gd

# OR Imagick
sudo apt-get install php-imagick  # Ubuntu/Debian
brew install imagemagick          # macOS
```

**Note**: If Intervention Image is not installed, Filexus will gracefully skip thumbnail generation without errors.

## Configuration

### Enable Thumbnails

Set in `config/filexus.php`:

```php
'generate_thumbnails' => true,
```

Or via environment variable:

```env
FILEXUS_GENERATE_THUMBNAILS=true
```

### Configure Thumbnail Sizes

Define thumbnail dimensions in `config/filexus.php`:

```php
'thumbnail_sizes' => [
    'small' => [150, 150],
    'medium' => [300, 300],
    'large' => [600, 600],
    'thumbnail' => [100, 100],
    'banner' => [1200, 400],
],
```

## How It Works

### Automatic Generation

When an image is uploaded, thumbnails are automatically generated:

```php
$file = $post->attach('gallery', $request->file('photo'));

// Check if thumbnails were generated
if ($file->hasThumbnails()) {
    // Get all thumbnail URLs
    $thumbnails = $file->thumbnailUrls();

    // [
    //     'small' => 'https://example.com/storage/posts/1/gallery/thumbnails/image_small.jpg',
    //     'medium' => 'https://example.com/storage/posts/1/gallery/thumbnails/image_medium.jpg',
    //     'large' => 'https://example.com/storage/posts/1/gallery/thumbnails/image_large.jpg',
    // ]
}
```

### Images Only

Thumbnails are only generated for image files:

```php
$image = $post->attach('gallery', $imageFile);
$doc = $post->attach('documents', $pdfFile);

assert($image->isImage() === true);
assert($image->hasThumbnails() === true);

assert($doc->isImage() === false);
assert($doc->hasThumbnails() === false);
```

### Storage Location

Thumbnails are stored in a `thumbnails/` subdirectory relative to the original file:

```
posts/1/gallery/
├── abc123.jpg           # Original file
└── thumbnails/
    ├── abc123_small.jpg
    ├── abc123_medium.jpg
    └── abc123_large.jpg
```

## Usage

### Get Thumbnail URLs

```php
// Get specific thumbnail
$smallUrl = $file->thumbnailUrl('small');
$mediumUrl = $file->thumbnailUrl('medium');

// Get all thumbnails
$thumbnails = $file->thumbnailUrls();
foreach ($thumbnails as $size => $url) {
    echo "<img src='{$url}' alt='{$size}'>";
}

// Check if file has thumbnails
if ($file->hasThumbnails()) {
    // Display thumbnails
}
```

### Blade Template Example

```blade
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

### Responsive Images

```blade
<img
    src="{{ $file->thumbnailUrl('medium') ?? $file->url() }}"
    srcset="
        {{ $file->thumbnailUrl('small') }} 150w,
        {{ $file->thumbnailUrl('medium') }} 300w,
        {{ $file->thumbnailUrl('large') }} 600w
    "
    sizes="(max-width: 600px) 150px, (max-width: 1200px) 300px, 600px"
    alt="{{ $file->original_name }}"
>
```

## Deduplication Support

Thumbnails work seamlessly with deduplication:

```php
config(['filexus.deduplicate' => true]);
config(['filexus.generate_thumbnails' => true]);

// First upload - generates thumbnails
$file1 = $post1->attach('gallery', $request->file('image'));

// Duplicate upload - reuses thumbnails
$file2 = $post2->attach('gallery', $request->file('same-image'));

assert($file1->metadata['thumbnails'] === $file2->metadata['thumbnails']);
```

## Metadata

Thumbnail paths are stored in the file's metadata:

```php
$metadata = $file->metadata;

// [
//     'thumbnails' => [
//         'small' => 'posts/1/gallery/thumbnails/abc123_small.jpg',
//         'medium' => 'posts/1/gallery/thumbnails/abc123_medium.jpg',
//         'large' => 'posts/1/gallery/thumbnails/abc123_large.jpg',
//     ]
// ]
```

## Automatic Cleanup

Thumbnails are automatically deleted when the parent file is deleted:

```php
$file = $post->attach('gallery', $imageFile);

// Delete the file
$file->delete();

// Physical file AND all thumbnails are deleted automatically
```

With deduplication enabled, thumbnails are only deleted when the last reference is removed:

```php
config(['filexus.deduplicate' => true]);
config(['filexus.generate_thumbnails' => true]);

$file1 = $post1->attach('gallery', $imageFile);
$file2 = $post2->attach('gallery', $sameImageFile);

// Delete first file - thumbnails still exist (file2 still references them)
$file1->delete();

// Delete second file - NOW thumbnails are deleted
$file2->delete();
```

## Customization

### Custom Thumbnail Sizes Per Upload

You cannot currently override thumbnail sizes per upload - they're global. To implement custom sizes per upload, extend the `FileUploader` service.

### Image Processing Options

The `ThumbnailGenerator` uses Intervention Image's `fit()` method, which:
- Crops and resizes to exact dimensions
- Centers the crop
- Maintains aspect ratio

To customize image processing, extend the `ThumbnailGenerator` service.

## Troubleshooting

### Thumbnails Not Generated

1. **Check Requirements**:
   ```bash
   $ composer show intervention/image
   # Should show package info

   $ php -m | grep -E 'gd|imagick'
   # Should show at least one driver
   ```

2. **Check Configuration**:
   ```php
   config('filexus.generate_thumbnails');  // Should return true
   ```

3. **Check File Type**:
   ```php
   $file->isImage();  // Must return true
   $file->mime;       // Must start with 'image/'
   ```

4. **Check Disk Permissions**:
   ```bash
   # Ensure storage is writable
   chmod -R 775 storage
   chown -R www-data:www-data storage
   ```

### Thumbnails Not Deleted

If deduplication is enabled, thumbnails may be retained if other files still reference the same physical file. This is by design.

## Performance Considerations

- Thumbnail generation happens **synchronously** during upload
- For large images or many sizes, this may slow down the request
- Consider using a **queue** for large-scale image processing

### Queue Example (Future Enhancement)

```php
// This is not currently implemented, but you could extend:

use Illuminate\Support\Facades\Queue;

FileUploaded::listen(function ($event) {
    if ($event->file->isImage() && config('filexus.generate_thumbnails_async')) {
Queue::push(new GenerateThumbnails($event->file));
    }
});
```

## See Also

- [File Deduplication](deduplication.md)
- [File Model API](../api/file-model.md)
- [Configuration](../configuration/global.md)
