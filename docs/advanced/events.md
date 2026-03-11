# Events

Filexus dispatches events throughout the file lifecycle, allowing you to hook into the upload, deletion, and management process.

## Available Events

### FileUploading

Dispatched before a file is uploaded to storage:

```php
namespace Filexus\Events;

class FileUploading
{
    public function __construct(
        public UploadedFile $uploadedFile,
        public string $collection,
        public Model $model
    ) {}
}
```

Use this to:
- Validate files before upload
- Modify metadata before storage
- Cancel uploads

### FileUploaded

Dispatched after a file is successfully uploaded:

```php
namespace Filexus\Events;

class FileUploaded
{
    public function __construct(
        public File $file,
        public Model $model
    ) {}
}
```

Use this to:
- Generate thumbnails
- Process images/videos
- Update related records
- Send notifications
- Log uploads

### FileDeleting

Dispatched before a file is deleted:

```php
namespace Filexus\Events;

class FileDeleting
{
    public function __construct(
        public File $file
    ) {}
}
```

Use this to:
- Archive files before deletion
- Create backups
- Prevent deletion under certain conditions

### FileDeleted

Dispatched after a file is deleted:

```php
namespace Filexus\Events;

class FileDeleted
{
    public function __construct(
        public File $file
    ) {}
}
```

Use this to:
- Update statistics
- Clean up related data
- Log deletions
- Send notifications

## Listening to Events

### Using Event Listeners

Create a listener:

```bash
php artisan make:listener ProcessUploadedImage
```

```php
namespace App\Listeners;

use Filexus\Events\FileUploaded;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class ProcessUploadedImage
{
    public function handle(FileUploaded $event)
    {
        $file = $event->file;

        // Only process images
        if (!$file->isImage()) {
            return;
        }

        // Generate thumbnail
        $path = Storage::disk($file->disk)->path($file->path);
        $thumbnailPath = str_replace('.', '_thumb.', $file->path);

        Image::make($path)
            ->fit(300, 300)
            ->save(Storage::disk($file->disk)->path($thumbnailPath));

        // Store thumbnail path in metadata
        $file->metadata = array_merge($file->metadata ?? [], [
            'thumbnail_path' => $thumbnailPath,
        ]);
        $file->save();
    }
}
```

Register in `app/Providers/EventServiceProvider.php`:

```php
use Filexus\Events\FileUploaded;
use App\Listeners\ProcessUploadedImage;

protected $listen = [
    FileUploaded::class => [
        ProcessUploadedImage::class,
    ],
];
```

### Using Closures

Register in `app/Providers/AppServiceProvider.php`:

```php
use Filexus\Events\FileUploaded;
use Illuminate\Support\Facades\Event;

public function boot()
{
    Event::listen(FileUploaded::class, function (FileUploaded $event) {
        logger('File uploaded', [
            'id' => $event->file->id,
            'name' => $event->file->original_name,
            'model' => get_class($event->model),
        ]);
    });
}
```

## Common Use Cases

### Generate Image Thumbnails

```php
use Filexus\Events\FileUploaded;
use Intervention\Image\Facades\Image;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    if ($event->file->isImage()) {
        $dimensions = [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [600, 600],
        ];

        foreach ($dimensions as $size => $dim) {
            $thumbnailPath = str_replace('.', "_{$size}.", $event->file->path);

            Image::make(Storage::disk($event->file->disk)->path($event->file->path))
                ->fit($dim[0], $dim[1])
                ->save(Storage::disk($event->file->disk)->path($thumbnailPath));
        }
    }
});
```

### Scan for Viruses

```php
use Filexus\Events\FileUploading;

Event::listen(FileUploading::class, function (FileUploading $event) {
    $scanner = app(VirusScanner::class);

    if ($scanner->isInfected($event->uploadedFile->path())) {
        throw new \Exception('File failed virus scan');
    }
});
```

### Update Upload Statistics

```php
use Filexus\Events\FileUploaded;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    $model = $event->model;

    if ($model instanceof User) {
        $model->increment('uploads_count');
        $model->increment('storage_used', $event->file->size);
    }
});
```

### Notify User on Upload

```php
use Filexus\Events\FileUploaded;
use App\Notifications\FileUploadedNotification;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    if ($event->model instanceof User) {
        $event->model->notify(new FileUploadedNotification($event->file));
    }
});
```

### Log File Deletions

```php
use Filexus\Events\FileDeleted;
use Illuminate\Support\Facades\Log;

Event::listen(FileDeleted::class, function (FileDeleted $event) {
    Log::channel('audit')->info('File deleted', [
        'id' => $event->file->id,
        'name' => $event->file->original_name,
        'size' => $event->file->size,
        'collection' => $event->file->collection,
        'deleted_at' => now()->toISOString(),
    ]);
});
```

### Archive Before Deletion

```php
use Filexus\Events\FileDeleting;

Event::listen(FileDeleting::class, function (FileDeleting $event) {
    $file = $event->file;

    // Copy to archive disk before deleting
    $archivePath = 'archive/' . date('Y/m/') . $file->path;

    Storage::disk('archive')->put(
        $archivePath,
        Storage::disk($file->disk)->get($file->path)
    );

    Log::info("File archived to: {$archivePath}");
});
```

### Verify File Upload

```php
use Filexus\Events\FileUploaded;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    $file = $event->file;

    // Verify file exists in storage
    if (!Storage::disk($file->disk)->exists($file->path)) {
        throw new \Exception('File upload verification failed');
    }

    // Verify file size matches
    $actualSize = Storage::disk($file->disk)->size($file->path);
    if ($actualSize !== $file->size) {
        throw new \Exception('File size mismatch');
    }
});
```

### Convert Videos

```php
use Filexus\Events\FileUploaded;
use App\Jobs\ConvertVideoJob;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    if ($event->file->isVideo()) {
        ConvertVideoJob::dispatch($event->file)->onQueue('video-processing');
    }
});
```

### Update Related Models

```php
use Filexus\Events\FileDeleted;

Event::listen(FileDeleted::class, function (FileDeleted $event) {
    // If profile picture deleted, clear user's profile_picture_id
    if ($event->file->collection === 'profile' && $event->file->fileable instanceof User) {
        $event->file->fileable->update(['profile_picture_id' => null]);
    }
});
```

### Calculate File Hashes

```php
use Filexus\Events\FileUploaded;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    $file = $event->file;

    // Calculate additional hashes
    $content = Storage::disk($file->disk)->get($file->path);

    $file->metadata = array_merge($file->metadata ?? [], [
        'md5' => md5($content),
        'sha1' => sha1($content),
    ]);
    $file->save();
});
```

## Queued Listeners

For heavy processing, use queued listeners:

```php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Filexus\Events\FileUploaded;

class GenerateThumbnails implements ShouldQueue
{
    public $queue = 'image-processing';

    public function handle(FileUploaded $event)
    {
        // Generate thumbnails asynchronously
    }
}
```

## Conditional Listeners

Listen only to specific collections:

```php
Event::listen(FileUploaded::class, function (FileUploaded $event) {
    // Only process avatar uploads
    if ($event->file->collection === 'avatar') {
        // Process avatar
    }
});
```

Listen only to specific models:

```php
Event::listen(FileUploaded::class, function (FileUploaded $event) {
    // Only process User uploads
    if ($event->model instanceof User) {
        // Process user file
    }
});
```

## Stopping Event Propagation

Return `false` from a listener to stop propagation:

```php
Event::listen(FileDeleting::class, function (FileDeleting $event) {
    // Prevent deletion of protected files
    if ($event->file->metadata['protected'] ?? false) {
        return false; // Cancel deletion
    }
});
```

## Testing with Events

Fake events in tests:

```php
use Illuminate\Support\Facades\Event;
use Filexus\Events\FileUploaded;

public function test_file_upload()
{
    Event::fake([FileUploaded::class]);

    $file = $user->attach('documents', $uploadedFile);

    Event::assertDispatched(FileUploaded::class, function ($event) use ($file) {
        return $event->file->id === $file->id;
    });
}
```

## Best Practices

1. **Use Queued Listeners**: For time-consuming tasks
2. **Handle Failures Gracefully**: Don't break uploads if processing fails
3. **Log Important Events**: Track uploads and deletions
4. **Validate Early**: Use `FileUploading` to reject bad files
5. **Keep Listeners Focused**: One task per listener
6. **Use Jobs for Heavy Work**: Dispatch jobs from listeners instead of doing work directly
