# API Reference: Exceptions

Filexus throws specific exceptions for different error scenarios to help you handle failures gracefully.

## Exception Classes

All exceptions are in the `Filexus\Exceptions` namespace and extend `Exception`.

---

## InvalidCollectionException

Thrown when a collection operation violates configuration rules.

### Namespace

```php
Filexus\Exceptions\InvalidCollectionException
```

### When Thrown

- Attempting to attach multiple files to a single-file collection
- Uploading to a collection that doesn't allow multiple files when one already exists

### Example

```php
use Filexus\Exceptions\InvalidCollectionException;

try {
    // 'avatar' is configured with multiple => false
    $user->attach('avatar', $firstImage);
    $user->attach('avatar', $secondImage); // Throws exception

} catch (InvalidCollectionException $e) {
    return back()->withErrors([
        'file' => 'Avatar collection only allows one file. Use replace() instead.'
    ]);
}
```

### Properties

```php
public string $collection;    // The collection name
public Model $model;          // The model instance
```

### Solution

Use `replace()` instead of `attach()` for single-file collections:

```php
// Instead of:
$user->attach('avatar', $newImage); // Exception!

// Use:
$user->replace('avatar', $newImage); // Works!
```

---

## FileUploadException

Thrown when file upload to storage fails.

### Namespace

```php
Filexus\Exceptions\FileUploadException
```

### When Thrown

- Storage disk is not accessible
- Disk space is full
- Permissions issues
- Network issues (for cloud storage)

### Example

```php
use Filexus\Exceptions\FileUploadException;

try {
    $file = $post->attach('images', $uploadedFile);

} catch (FileUploadException $e) {
    Log::error('File upload failed', [
        'message' => $e->getMessage(),
        'file' => $uploadedFile->getClientOriginalName(),
    ]);

    return back()->withErrors([
        'file' => 'Failed to upload file. Please try again.'
    ]);
}
```

### Properties

```php
public UploadedFile $file;      // The file that failed to upload
public string $disk;             // The storage disk
public ?string $path;            // The intended path (if known)
```

---

## FileSizeException

Thrown when uploaded file exceeds maximum size limit.

### Namespace

```php
Filexus\Exceptions\FileSizeException
```

### When Thrown

- File size exceeds collection's `max_file_size` configuration
- File size exceeds global `max_file_size` configuration

### Example

```php
use Filexus\Exceptions\FileSizeException;

try {
    // max_file_size is 2048 KB (2 MB)
    $file = $user->attach('avatar', $hugeImage); // 10 MB

} catch (FileSizeException $e) {
    return back()->withErrors([
        'file' => "File is too large. Maximum size is {$e->maxSize} KB."
    ]);
}
```

### Properties

```php
public int $fileSize;        // Actual file size in bytes
public int $maxSize;         // Maximum allowed size in bytes
public string $collection;   // The collection name
```

### Helpful Response

```php
catch (FileSizeException $e) {
    $maxMB = round($e->maxSize / 1024, 2);
    $actualMB = round($e->fileSize / 1024, 2);

    return back()->withErrors([
        'file' => "File size ({$actualMB} MB) exceeds maximum allowed size ({$maxMB} MB)"
    ]);
}
```

---

## InvalidMimeTypeException

Thrown when file MIME type is not allowed.

### Namespace

```php
Filexus\Exceptions\InvalidMimeTypeException
```

### When Thrown

- File MIME type not in collection's `allowed_mimes` list
- File MIME type not in global `allowed_mimes` list

### Example

```php
use Filexus\Exceptions\InvalidMimeTypeException;

try {
    // allowed_mimes: ['image/jpeg', 'image/png']
    $file = $user->attach('avatar', $pdfFile); // PDF not allowed

} catch (InvalidMimeTypeException $e) {
    return back()->withErrors([
        'file' => "File type '{$e->mimeType}' is not allowed."
    ]);
}
```

### Properties

```php
public string $mimeType;         // Actual MIME type
public array $allowedMimes;      // List of allowed MIME types
public string $collection;       // The collection name
```

### User-Friendly Response

```php
catch (InvalidMimeTypeException $e) {
    $allowed = implode(', ', $e->allowedMimes);

    return back()->withErrors([
        'file' => "Invalid file type. Allowed types: {$allowed}"
    ]);
}
```

---

## FileNotFoundException

Thrown when attempting to access a file that doesn't exist in storage.

### Namespace

```php
Filexus\Exceptions\FileNotFoundException
```

### When Thrown

- File record exists in database but physical file is missing
- Corrupted storage or file was manually deleted

### Example

```php
use Filexus\Exceptions\FileNotFoundException;

try {
    $content = Storage::disk($file->disk)->get($file->path);

} catch (FileNotFoundException $e) {
    Log::error('File missing from storage', [
        'file_id' => $e->file->id,
        'path' => $e->file->path,
    ]);

    // Clean up orphaned database record
    $e->file->delete();

    return response()->json([
        'error' => 'File no longer available'
    ], 404);
}
```

### Properties

```php
public File $file;           // The File model
public string $path;         // The missing file path
```

---

## HashCalculationException

Thrown when hash calculation fails.

### Namespace

```php
Filexus\Exceptions\HashCalculationException
```

### When Thrown

- File is corrupted or unreadable
- Permissions issue preventing file read
- File was deleted during upload

### Example

```php
use Filexus\Exceptions\HashCalculationException;

try {
    $file = $post->attach('documents', $uploadedFile);

} catch (HashCalculationException $e) {
    Log::error('Hash calculation failed', [
        'file' => $e->filePath,
        'message' => $e->getMessage(),
    ]);

    return back()->withErrors([
        'file' => 'File appears to be corrupted. Please try again.'
    ]);
}
```

### Properties

```php
public string $filePath;     // Path to file that caused error
```

---

## Handling Multiple Exceptions

### Try-Catch All Filexus Exceptions

```php
use Filexus\Exceptions\InvalidCollectionException;
use Filexus\Exceptions\FileUploadException;
use Filexus\Exceptions\FileSizeException;
use Filexus\Exceptions\InvalidMimeTypeException;

try {
    $file = $post->attach('gallery', $request->file('image'));

    return response()->json(['success' => true, 'file' => $file]);

} catch (InvalidCollectionException $e) {
    return response()->json([
        'error' => 'Collection does not allow multiple files'
    ], 422);

} catch (FileSizeException $e) {
    return response()->json([
        'error' => "File too large. Max: {$e->maxSize} bytes"
    ], 422);

} catch (InvalidMimeTypeException $e) {
    return response()->json([
        'error' => "Invalid file type: {$e->mimeType}"
    ], 422);

} catch (FileUploadException $e) {
    Log::error('Upload failed', ['exception' => $e]);

    return response()->json([
        'error' => 'Upload failed. Please try again.'
    ], 500);
}
```

### Catch Base Exception

All Filexus exceptions extend `Exception`, so you can catch them all:

```php
try {
    $file = $post->attach('images', $uploadedFile);

} catch (\Exception $e) {
    // Log all exceptions
    Log::error('File operation failed', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
    ]);

    return back()->withErrors(['file' => 'An error occurred']);
}
```

---

## Controller Error Handling

### Base Controller Method

```php
namespace App\Http\Controllers;

use Filexus\Exceptions\InvalidCollectionException;
use Filexus\Exceptions\FileSizeException;
use Filexus\Exceptions\InvalidMimeTypeException;
use Filexus\Exceptions\FileUploadException;

abstract class Controller
{
    protected function handleFileException(\Exception $e)
    {
        return match (true) {
            $e instanceof InvalidCollectionException =>
                back()->withErrors(['file' => 'Cannot add multiple files to this collection']),

            $e instanceof FileSizeException =>
                back()->withErrors(['file' => "File too large (max: {$e->maxSize} KB)"]),

            $e instanceof InvalidMimeTypeException =>
                back()->withErrors(['file' => 'Invalid file type']),

            $e instanceof FileUploadException =>
                back()->withErrors(['file' => 'Upload failed']),

            default =>
                back()->withErrors(['file' => 'An error occurred']),
        };
    }
}
```

Usage:

```php
public function upload(Request $request)
{
    try {
        $file = $post->attach('images', $request->file('image'));
        return redirect()->back()->with('success', 'File uploaded!');
    } catch (\Exception $e) {
        return $this->handleFileException($e);
    }
}
```

---

## API Error Responses

### JSON Error Handler

```php
protected function jsonFileError(\Exception $e): JsonResponse
{
    $statusCode = match (true) {
        $e instanceof InvalidCollectionException,
        $e instanceof FileSizeException,
        $e instanceof InvalidMimeTypeException => 422,

        $e instanceof FileNotFoundException => 404,

        $e instanceof FileUploadException,
        $e instanceof HashCalculationException => 500,

        default => 400,
    };

    return response()->json([
        'success' => false,
        'error' => $e->getMessage(),
        'exception' => class_basename($e),
    ], $statusCode);
}
```

---

## Custom Exception Handling

### Global Exception Handler

Register in `app/Exceptions/Handler.php`:

```php
use Filexus\Exceptions\FileUploadException;use Filexus\Exceptions\InvalidCollectionException;

public function register()
{
    $this->renderable(function (InvalidCollectionException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Collection does not allow multiple files',
                'collection' => $e->collection,
            ], 422);
        }
    });

    $this->renderable(function (FileUploadException $e, $request) {
        Log::error('File upload failed', [
            'disk' => $e->disk,
            'path' => $e->path,
            'exception' => $e,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Upload failed',
            ], 500);
        }
    });
}
```

---

## Testing Exceptions

### Assert Exceptions Thrown

```php
use Filexus\Exceptions\InvalidCollectionException;

it('throws exception for multiple files in single collection', function () {
    $user = User::factory()->create();
    $file1 = UploadedFile::fake()->image('avatar1.jpg');
    $file2 = UploadedFile::fake()->image('avatar2.jpg');

    $user->attach('avatar', $file1);

    expect(fn() => $user->attach('avatar', $file2))
        ->toThrow(InvalidCollectionException::class);
});
```

### Test Exception Properties

```php
it('provides correct exception details', function () {
    try {
        $user->attach('avatar', $tooLargeFile);
    } catch (FileSizeException $e) {
        expect($e->collection)->toBe('avatar');
        expect($e->maxSize)->toBe(2048000); // 2MB
        expect($e->fileSize)->toBeGreaterThan($e->maxSize);
    }
});
```

---

## Best Practices

1. **Catch Specific Exceptions**: Handle each exception type appropriately
2. **Log Errors**: Always log exceptions for debugging
3. **User-Friendly Messages**: Don't expose technical details to users
4. **Validate Early**: Validate files before attempting upload
5. **Graceful Degradation**: Provide fallback behavior when uploads fail
6. **Test Exception Paths**: Write tests for error scenarios

---

## See Also

- [Basic Operations](/usage/basic) - File upload and management
- [Configuration](/configuration/global) - Size and MIME type limits
- [Events](/api/events) - Event-based error handling
