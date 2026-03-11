# Per-Model Configuration

Override global configuration on a per-model basis using the `$fileCollections` property.

## Basic Configuration

Define collection-specific rules in your model:

```php
use Illuminate\Database\Eloquent\Model;
use Filexus\Traits\HasFiles;

class Post extends Model
{
    use HasFiles;

    protected $fileCollections = [
        'avatar' => [
            'multiple' => false,
            'max_file_size' => 2048, // 2MB
            'allowed_mimes' => ['image/jpeg', 'image/png'],
        ],
        'gallery' => [
            'multiple' => true,
            'max_file_size' => 5120, // 5MB
        ],
        'documents' => [
            'multiple' => true,
            'allowed_mimes' => ['application/pdf'],
        ],
    ];
}
```

## Configuration Options

### multiple

Whether the collection accepts multiple files.

```php
'avatar' => [
    'multiple' => false, // Only one file
],
'gallery' => [
    'multiple' => true, // Multiple files
],
```

When `multiple => false`:
- Attempting to attach a second file throws `InvalidCollectionException`
- Use `replace()` method instead of `attach()` for updates

### max_file_size

Maximum file size in kilobytes.

```php
'thumbnail' => [
    'max_file_size' => 2048, // 2MB
],
'videos' => [
    'max_file_size' => 102400, // 100MB
],
```

Exceeding the limit throws `FileUploadException`.

### allowed_mimes

Array of allowed MIME types.

```php
'images' => [
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],
],
```

Empty array allows all types:

```php
'documents' => [
    'allowed_mimes' => [], // Allow all
],
```

## Complete Examples

### User Model

```php
class User extends Model
{
    use HasFiles;

    protected $fileCollections = [
        'avatar' => [
            'multiple' => false,
            'max_file_size' => 2048, // 2MB
            'allowed_mimes' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
        ],
        'resume' => [
            'multiple' => false,
            'max_file_size' => 5120, // 5MB
            'allowed_mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ],
    ];
}
```

### Product Model

```php
class Product extends Model
{
    use HasFiles;

    protected $fileCollections = [
        'featured_image' => [
            'multiple' => false,
            'max_file_size' => 3072, // 3MB
            'allowed_mimes' => ['image/jpeg', 'image/png'],
        ],
        'gallery' => [
            'multiple' => true,
            'max_file_size' => 5120, // 5MB
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'manuals' => [
            'multiple' => true,
            'max_file_size' => 10240, // 10MB
            'allowed_mimes' => ['application/pdf'],
        ],
    ];
}
```

### Article Model

```php
class Article extends Model
{
    use HasFiles;

    protected $fileCollections = [
        'featured_image' => [
            'multiple' => false,
            'max_file_size' => 2048,
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'inline_images' => [
            'multiple' => true,
            'max_file_size' => 3072,
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif'],
        ],
        'attachments' => [
            'multiple' => true,
            'max_file_size' => 20480, // 20MB
            'allowed_mimes' => [
                'application/pdf',
                'application/zip',
                'application/x-rar-compressed',
            ],
        ],
    ];
}
```

## Configuration Inheritance

Per-model configuration overrides global configuration:

```php
// config/filexus.php
'collections' => [
    'default' => [
        'multiple' => true,
        'max_file_size' => 10240,
    ],
    'avatar' => [
        'multiple' => false,
        'max_file_size' => 2048,
    ],
],

// User model
protected $fileCollections = [
    'avatar' => [
        'max_file_size' => 3072, // This overrides global 2048
        // Inherits 'multiple' => false from global
    ],
];
```

## Dynamic Configuration

Configure collections dynamically using model methods:

```php
class Post extends Model
{
    use HasFiles;

    public function getFileCollections(): array
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

## Validation in Controllers

Combine with Laravel validation:

```php
public function store(Request $request)
{
    $request->validate([
        'title' => 'required',
        'avatar' => 'required|image|max:2048',
        'documents.*' => 'file|mimes:pdf|max:10240',
    ]);

    $user = User::create($request->only('title'));

    try {
        $user->attach('avatar', $request->file('avatar'));

        if ($request->hasFile('documents')) {
            $user->attachMany('documents', $request->file('documents'));
        }
    } catch (\Filexus\Exceptions\FileUploadException $e) {
        return back()->withErrors(['file' => $e->getMessage()]);
    }

    return redirect()->route('users.show', $user);
}
```

## Common MIME Types

### Images
```php
'allowed_mimes' => [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'image/bmp',
],
```

### Documents
```php
'allowed_mimes' => [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'text/csv',
],
```

### Audio
```php
'allowed_mimes' => [
    'audio/mpeg',
    'audio/mp4',
    'audio/ogg',
    'audio/wav',
    'audio/webm',
],
```

### Video
```php
'allowed_mimes' => [
    'video/mp4',
    'video/mpeg',
    'video/quicktime',
    'video/x-msvideo',
    'video/webm',
],
```

### Archives
```php
'allowed_mimes' => [
    'application/zip',
    'application/x-rar-compressed',
    'application/x-7z-compressed',
    'application/x-tar',
    'application/gzip',
],
```

## Best Practices

1. **Always set max_file_size**: Prevent abuse and storage issues
2. **Restrict allowed_mimes**: Only allow what's needed for security
3. **Use single-file collections**: For unique items like avatars
4. **Validate in controllers**: Add Laravel validation as first line of defense
5. **Document requirements**: Make file requirements clear to users
