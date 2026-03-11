# Custom Path Generator

Create custom file path generation logic by extending the `FilePathGenerator` class.

## Default Path Generation

By default, Filexus generates paths in this format:

```
/{model}/{id}/{collection}/{uuid}.{extension}
```

Example:
```
/Post/123/gallery/9d4e7a32-1234-4567-89ab-cdef01234567.jpg
```

## Creating a Custom Generator

### Step 1: Create the Generator Class

```php
namespace App\Services;

use Filexus\Services\FilePathGenerator as BaseGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class CustomPathGenerator extends BaseGenerator
{
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = substr(md5($file->getClientOriginalName() . time()), 0, 10);

        return sprintf(
            '%s/%s/%s.%s',
            $collection,
            date('Y/m'),
            $hash,
            $extension
        );
    }
}
```

### Step 2: Register in Configuration

Update `config/filexus.php`:

```php
'path_generator' => \App\Services\CustomPathGenerator::class,
```

### Step 3: Use It

No changes needed in your code. Filexus automatically uses your custom generator:

```php
$file = $post->attach('gallery', $uploadedFile);
echo $file->path; // "gallery/2024/03/abc123def4.jpg"
```

## Example Generators

### Date-Based Organization

```php
class DateBasedPathGenerator extends FilePathGenerator
{
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        $modelName = class_basename($model);
        $extension = $file->getClientOriginalExtension();
        $uuid = \Illuminate\Support\Str::uuid();

        return sprintf(
            '%s/%s/%s/%s/%s.%s',
            $modelName,
            date('Y'),
            date('m'),
            $collection,
            $uuid,
            $extension
        );
    }
}
```

Output: `Post/2024/03/gallery/550e8400-e29b-41d4-a716-446655440000.jpg`

### Tenant-Based Organization

```php
class TenantPathGenerator extends FilePathGenerator
{
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        $tenantId = auth()->user()?->tenant_id ?? 'default';
        $modelName = class_basename($model);
        $extension = $file->getClientOriginalExtension();
        $uuid = \Illuminate\Support\Str::uuid();

        return sprintf(
            'tenant-%s/%s/%s/%s.%s',
            $tenantId,
            $modelName,
            $collection,
            $uuid,
            $extension
        );
    }
}
```

Output: `tenant-42/Post/gallery/550e8400-e29b-41d4-a716-446655440000.jpg`

### Flat Structure with Timestamps

```php
class FlatPathGenerator extends FilePathGenerator
{
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d_His');
        $random = \Illuminate\Support\Str::random(8);

        return sprintf(
            '%s/%s_%s.%s',
            $collection,
            $timestamp,
            $random,
            $extension
        );
    }
}
```

Output: `gallery/2024-03-11_143022_aB3dEf9h.jpg`

### Original Filename Preservation

```php
class PreserveFilenameGenerator extends FilePathGenerator
{
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        $modelName = class_basename($model);
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $hash = substr(md5($originalName . time()), 0, 8);

        // Sanitize filename
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);

        return sprintf(
            '%s/%s/%s/%s_%s.%s',
            $modelName,
            $model->id,
            $collection,
            $sanitized,
            $hash,
            $extension
        );
    }
}
```

Output: `Post/123/gallery/my_photo_a1b2c3d4.jpg`

### Hash-Based Sharding

Distribute files across multiple directories for better filesystem performance:

```php
class ShardedPathGenerator extends FilePathGenerator
{
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        $uuid = \Illuminate\Support\Str::uuid();
        $extension = $file->getClientOriginalExtension();

        // Use first 2 chars of UUID for sharding
        $shard = substr($uuid, 0, 2);

        return sprintf(
            '%s/%s/%s/%s.%s',
            $collection,
            $shard,
            class_basename($model),
            $uuid,
            $extension
        );
    }
}
```

Output: `gallery/9d/Post/9d4e7a32-1234-4567-89ab-cdef01234567.jpg`

## Advanced: Per-Collection Generators

Create a generator that uses different strategies per collection:

```php
class SmartPathGenerator extends FilePathGenerator
{
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        return match ($collection) {
            'avatar', 'thumbnail' => $this->generateSimplePath($model, $collection, $file),
            'gallery', 'images' => $this->generateDateBasedPath($model, $collection, $file),
            'documents', 'attachments' => $this->generatePreservedPath($model, $collection, $file),
            default => $this->generateDefaultPath($model, $collection, $file),
        };
    }

    protected function generateSimplePath(Model $model, string $collection, UploadedFile $file): string
    {
        $modelName = class_basename($model);
        $extension = $file->getClientOriginalExtension();

        return sprintf('%s/%s/%s.%s', $modelName, $model->id, $collection, $extension);
    }

    protected function generateDateBasedPath(Model $model, string $collection, UploadedFile $file): string
    {
        $uuid = \Illuminate\Support\Str::uuid();
        $extension = $file->getClientOriginalExtension();

        return sprintf(
            '%s/%s/%s/%s.%s',
            $collection,
            date('Y-m'),
            class_basename($model),
            $uuid,
            $extension
        );
    }

    protected function generatePreservedPath(Model $model, string $collection, UploadedFile $file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $timestamp = time();

        return sprintf(
            '%s/%s/%s_%s.%s',
            class_basename($model),
            $collection,
            $sanitized,
            $timestamp,
            $extension
        );
    }

    protected function generateDefaultPath(Model $model, string $collection, UploadedFile $file): string
    {
        // Fallback to default behavior
        return parent::generate($model, $collection, $file);
    }
}
```

## Testing Your Generator

```php
use Tests\TestCase;
use App\Services\CustomPathGenerator;
use App\Models\Post;
use Illuminate\Http\UploadedFile;

class CustomPathGeneratorTest extends TestCase
{
    public function test_generates_correct_path()
    {
        $generator = new CustomPathGenerator();
        $post = Post::factory()->create(['id' => 123]);
        $file = UploadedFile::fake()->image('test.jpg');

        $path = $generator->generate($post, 'gallery', $file);

        $this->assertStringContainsString('gallery', $path);
        $this->assertStringContainsString('.jpg', $path);
    }
}
```

## Best Practices

1. **Ensure Uniqueness**: Always include unique elements (UUID, timestamp + random) to avoid collisions
2. **Sanitize Input**: Clean user-provided filenames to prevent security issues
3. **Consider Performance**: For large-scale apps, use sharding to distribute files
4. **Maintain Extensions**: Preserve original file extensions for proper MIME type detection
5. **Document Your Structure**: Make the path structure clear for maintenance
6. **Test Thoroughly**: Ensure paths work across different storage drivers (local, S3, etc.)

## Accessing Generated Paths

The generated path is stored in the `path` column:

```php
$file = $post->attach('gallery', $uploadedFile);

echo $file->path; // Generated by your custom generator
echo $file->url(); // Full URL using Storage facade
```

## Migration Considerations

If changing path generators:

1. **New files** use the new generator
2. **Existing files** keep their old paths
3. Consider creating a command to migrate old files:

```php
php artisan make:command MigrateFilePaths
```

```php
public function handle()
{
    $files = File::all();

    foreach ($files as $file) {
        $oldPath = $file->path;
        $newPath = app(FilePathGenerator::class)->generate(
            $file->fileable,
            $file->collection,
            // You'll need to handle this carefully
        );

        Storage::disk($file->disk)->move($oldPath, $newPath);

        $file->update(['path' => $newPath]);
    }
}
```
