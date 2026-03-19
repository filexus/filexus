# Getting Started

Filexus is a production-ready Laravel package that provides a simple and elegant file attachment system for Eloquent models. This guide will help you understand what Filexus is and whether it's the right solution for your project.

## What is Filexus?

Filexus allows you to attach files to any Eloquent model in your Laravel application. Whether you need to add profile pictures to users, attach documents to posts, or manage image galleries, Filexus provides a clean and consistent API.

## Key Features

### 🎯 Named Collections

Organize files into logical collections:

```php
$user->attach('avatar', $avatarFile);
$post->attachMany('gallery', $imageFiles);
$document->attach('attachments', $pdfFile);
```

### 🔢 Flexible Configuration

Configure collections as single-file or multi-file:

```php
protected $fileCollections = [
    'avatar' => ['multiple' => false], // Only one avatar
    'gallery' => ['multiple' => true], // Multiple gallery images
];
```

### 🗑️ Automatic Cleanup

Files are automatically deleted when models are removed:

```php
$post->delete(); // All attached files are cleaned up automatically
```

### 📊 Rich Metadata

Access comprehensive file information:

```php
$file->original_name;  // "photo.jpg"
$file->mime;           // "image/jpeg"
$file->size;           // 1024000
$file->hash;           // SHA256 hash
$file->human_readable_size; // "1000.0 KB"
```

### 🔍 Powerful Queries

Use query scopes for efficient file operations:

```php
File::whereCollection('gallery')->get();
File::whereExpired()->get();
File::whereOrphaned()->get();
```

## When to Use Filexus

Filexus is perfect for:

- **User Profiles**: Profile pictures, resumes, identity documents
- **Content Management**: Featured images, galleries, media attachments
- **E-commerce**: Product images, downloadable files, invoices
- **Document Management**: Contracts, reports, legal documents
- **Any Eloquent Model**: Attach files to any model in your application

## When Not to Use Filexus

Consider alternatives if you need:

- **Image Processing**: Filexus doesn't handle image manipulation (use it alongside Intervention Image or similar)
- **CDN Integration**: While Filexus works with Laravel Storage (which supports CDNs), specialized packages may offer deeper integration
- **Advanced Media Libraries**: If you need complex media management with transformations, consider packages like Laravel Media Library

## Architecture

Filexus follows clean architecture principles:

- **Traits**: Simple integration with `HasFiles` trait
- **Services**: Business logic in dedicated service classes
- **Events**: Lifecycle hooks for extensibility
- **Configuration**: Flexible global and per-model settings
- **Storage**: Built on Laravel's Storage system

## Laravel Version Compatibility

| Filexus | Laravel | PHP  |
| ------- | ------- | ---- |
| 1.x     | 11+     | 8.3+ |

## Next Steps

Ready to get started? Continue to [Installation](/installation) to set up Filexus in your project.
