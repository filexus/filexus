# API Reference: Events

Filexus dispatches events throughout the file lifecycle for extensibility and integration.

## Event Classes

All events are in the `Filexus\Events` namespace.

---

## FileUploading

Dispatched **before** a file is uploaded to storage.

### Namespace

```php
Filexus\Events\FileUploading
```

### Properties

```php
public UploadedFile $uploadedFile;
public string $collection;
public Model $model;
```

### Description

- **when**: Before file is stored on disk
- **use for**: Validation, virus scanning, preprocessing, canceling uploads

### Example

```php
use Filexus\Events\FileUploading;

Event::listen(FileUploading::class, function (FileUploading $event) {
    // $event->uploadedFile - The uploaded file being processed
    // $event->collection - Collection name ('avatar', 'documents', etc.)
    // $event->model - Parent model (User, Post, etc.)

    // Validate file extension
    $allowedExtensions = ['jpg', 'png', 'pdf'];
    $extension = $event->uploadedFile->extension();

    if (!in_array($extension, $allowedExtensions)) {
        throw new \Exception("File type not allowed: {$extension}");
    }
});
```

### Use Cases

**Virus Scanning:**
```php
Event::listen(FileUploading::class, function (FileUploading $event) {
    $scanner = app(VirusScanner::class);

    if ($scanner->isInfected($event->uploadedFile->path())) {
        throw new \Exception('File failed virus scan');
    }
});
```

**Size Limit Enforcement:**
```php
Event::listen(FileUploading::class, function (FileUploading $event) {
    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($event->uploadedFile->getSize() > $maxSize) {
        throw new \Exception('File exceeds maximum size limit');
    }
});
```

**Custom Validation:**
```php
Event::listen(FileUploading::class, function (FileUploading $event) {
    if ($event->collection === 'profile_photo') {
        // Validate image dimensions
        $dimensions = getimagesize($event->uploadedFile->path());

        if ($dimensions[0] < 200 || $dimensions[1] < 200) {
            throw new \Exception('Profile photo must be at least 200x200 pixels');
        }
    }
});
```

---

## FileUploaded

Dispatched **after** a file is successfully uploaded and saved.

### Namespace

```php
Filexus\Events\FileUploaded
```

### Properties

```php
public File $file;
public Model $model;
```

### Description

- **When**: After file is stored and database record created
- **Use For**: Post-processing, thumbnails, notifications, analytics

### Example

```php
use Filexus\Events\FileUploaded;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    // $event->file - The created File model instance
    // $event->model - Parent model the file was attached to

    Log::info('File uploaded', [
        'file_id' => $event->file->id,
        'name' => $event->file->original_name,
        'model' => get_class($event->model),
        'model_id' => $event->model->getKey(),
    ]);
});
```

### Use Cases

**Generate Thumbnails:**
```php
use Intervention\Image\Facades\Image;

Event::listen(FileUploaded::class, function (FileUploaded $event) {
    if ($event->file->isImage()) {
        $path = Storage::disk($event->file->disk)->path($event->file->path);
        $thumbnailPath = str_replace('.', '_thumb.', $event->file->path);

        Image::make($path)
            ->fit(300, 300)
            ->save(Storage::disk($event->file->disk)->path($thumbnailPath));

        $event->file->metadata = array_merge($event->file->metadata ?? [], [
            'thumbnail_path' => $thumbnailPath,
        ]);
        $event->file->save();
    }
});
```

**Send Notification:**
```php
Event::listen(FileUploaded::class, function (FileUploaded $event) {
    if ($event->model instanceof User) {
        $event->model->notify(new FileUploadedNotification($event->file));
    }
});
```

**Update Statistics:**
```php
Event::listen(FileUploaded::class, function (FileUploaded $event) {
    if ($event->model instanceof User) {
        $event->model->increment('uploads_count');
        $event->model->increment('storage_used', $event->file->size);
    }
});
```

**Queue Processing:**
```php
Event::listen(FileUploaded::class, function (FileUploaded $event) {
    if ($event->file->isVideo()) {
        ProcessVideoJob::dispatch($event->file)->onQueue('video-encoding');
    }
});
```

---

## FileDeleting

Dispatched **before** a file is deleted.

### Namespace

```php
Filexus\Events\FileDeleting
```

### Properties

```php
public File $file;
```

### Description

- **When**: Before file is removed from storage and database
- **Use For**: Archiving, backups, cleanup related data, preventing deletion

### Example

```php
use Filexus\Events\FileDeleting;

Event::listen(FileDeleting::class, function (FileDeleting $event) {
    // $event->file - The File model about to be deleted

    Log::warning('File being deleted', [
        'file_id' => $event->file->id,
        'name' => $event->file->original_name,
        'size' => $event->file->size,
    ]);
});
```

### Use Cases

**Archive Before Deletion:**
```php
Event::listen(FileDeleting::class, function (FileDeleting $event) {
    $file = $event->file;
    $archivePath = 'archive/' . date('Y/m/') . $file->path;

    Storage::disk('archive')->put(
        $archivePath,
        Storage::disk($file->disk)->get($file->path)
    );

    Log::info("File archived to: {$archivePath}");
});
```

**Prevent Deletion:**
```php
Event::listen(FileDeleting::class, function (FileDeleting $event) {
    if ($event->file->metadata['protected'] ?? false) {
        // Cancel deletion by returning false
        return false;
    }
});
```

**Clean Up Related Data:**
```php
Event::listen(FileDeleting::class, function (FileDeleting $event) {
    // Delete associated thumbnails
    if (isset($event->file->metadata['thumbnail_path'])) {
        Storage::disk($event->file->disk)->delete($event->file->metadata['thumbnail_path']);
    }
});
```

---

## FileDeleted

Dispatched **after** a file is deleted.

### Namespace

```php
Filexus\Events\FileDeleted
```

### Properties

```php
public File $file;
```

### Description

- **When**: After file is removed from storage and database
- **Use For**: Audit logging, statistics update, cleanup notifications

### Example

```php
use Filexus\Events\FileDeleted;

Event::listen(FileDeleted::class, function (FileDeleted $event) {
    // $event->file - The deleted File model (soft deleted, attributes still accessible)

    Log::info('File deleted', [
        'file_id' => $event->file->id,
        'name' => $event->file->original_name,
    ]);
});
```

### Use Cases

**Update Statistics:**
```php
Event::listen(FileDeleted::class, function (FileDeleted $event) {
    if ($event->file->fileable instanceof User) {
        $event->file->fileable->decrement('storage_used', $event->file->size);
    }
});
```

**Audit Log:**
```php
Event::listen(FileDeleted::class, function (FileDeleted $event) {
    AuditLog::create([
        'action' => 'file_deleted',
        'file_id' => $event->file->id,
        'file_name' => $event->file->original_name,
        'deleted_by' => auth()->id(),
        'deleted_at' => now(),
    ]);
});
```

**Send Notification:**
```php
Event::listen(FileDeleted::class, function (FileDeleted $event) {
    if ($event->file->isExpired()) {
        $event->file->fileable->notify(
            new FileExpiredNotification($event->file)
        );
    }
});
```

---

## Registering Listeners

### EventServiceProvider

Register in `app/Providers/EventServiceProvider.php`:

```php
use Filexus\Events\FileUploaded;
use Filexus\Events\FileDeleted;
use App\Listeners\ProcessUploadedImage;
use App\Listeners\LogFileDeletion;

protected $listen = [
    FileUploaded::class => [
        ProcessUploadedImage::class,
        GenerateThumbnails::class,
        SendUploadNotification::class,
    ],
    FileDeleted::class => [
        LogFileDeletion::class,
        UpdateStorageStatistics::class,
    ],
];
```

### Event Listeners

Create listener class:

```bash
php artisan make:listener ProcessUploadedImage
```

```php
namespace App\Listeners;

use Filexus\Events\FileUploaded;

class ProcessUploadedImage
{
    public function handle(FileUploaded $event)
    {
        if (!$event->file->isImage()) {
            return;
        }

        // Process image...
    }
}
```

### Queued Listeners

For time-consuming operations:

```php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Filexus\Events\FileUploaded;

class GenerateThumbnails implements ShouldQueue
{
    public $queue = 'image-processing';
    public $tries = 3;

    public function handle(FileUploaded $event)
    {
        // Heavy processing...
    }
}
```

### Closure Listeners

Register in `boot()` method:

```php
use Filexus\Events\FileUploaded;
use Illuminate\Support\Facades\Event;

public function boot()
{
    Event::listen(FileUploaded::class, function (FileUploaded $event) {
        // Quick processing...
    });
}
```

---

## Event Flow

```
Upload Request
      ↓
FileUploading (before storage)
      ↓
  [File stored to disk]
      ↓
  [Database record created]
      ↓
FileUploaded (after storage)
```

```
Delete Request
      ↓
FileDeleting (before deletion)
      ↓
  [File removed from disk]
      ↓
  [Database record deleted]
      ↓
FileDeleted (after deletion)
```

---

## Testing Events

### Fake Events

```php
use Illuminate\Support\Facades\Event;
use Filexus\Events\FileUploaded;

public function test_file_upload()
{
    Event::fake([FileUploaded::class]);

    $file = $user->attach('documents', $uploadedFile);

    Event::assertDispatched(FileUploched::class, function ($event) use ($file) {
        return $event->file->id === $file->id;
    });
}
```

### Assert Event Properties

```php
Event::fake();

$post->attach('images', $uploadedFile);

Event::assertDispatched(FileUploaded::class, function ($event) use ($post) {
    return $event->model->id === $post->id
        && $event->file->collection === 'images';
});
```

---

## Best Practices

1. **Use Queued Listeners**: For heavy processing (thumbnails, video encoding)
2. **Handle Failures Gracefully**: Don't break uploads if processing fails
3. **Log Exceptions**: Track issues in event listeners
4. **Keep Listeners Focused**: One responsibility per listener
5. **Test Event Dispatch**: Ensure events fire correctly
6. **Use Jobs for Heavy Work**: Dispatch jobs from listeners instead of doing work directly

---

## Complete Example

```php
// EventServiceProvider.php
protected $listen = [
    FileUploaded::class => [
        GenerateThumbnails::class,
        ScanForViruses::class,
        UpdateStatistics::class,
        NotifyUser::class,
    ],
    FileDeleted::class => [
        LogDeletion::class,
        CleanupThumbnails::class,
    ],
];

// GenerateThumbnails.php
class GenerateThumbnails implements ShouldQueue
{
    public function handle(FileUploaded $event)
    {
        if (!$event->file->isImage()) {
            return;
        }

        $sizes = ['small' => 150, 'medium' => 300, 'large' => 600];

        foreach ($sizes as $name => $size) {
            $this->generateThumbnail($event->file, $name, $size);
        }
    }

    private function generateThumbnail($file, $name, $size)
    {
        $path = Storage::disk($file->disk)->path($file->path);
        $thumbPath = str_replace('.', "_{$name}.", $file->path);

        Image::make($path)
            ->fit($size, $size)
            ->save(Storage::disk($file->disk)->path($thumbPath));

        $file->metadata = array_merge($file->metadata ?? [], [
            "thumbnail_{$name}" => $thumbPath,
        ]);
        $file->save();
    }
}
```

## See Also

- [Events Guide](/advanced/events) - Event system overview and use cases
- [HasFiles Trait](/api/trait-methods) - Methods that dispatch events
- [FilexusManager](/api/manager) - Manager operations
