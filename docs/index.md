---
layout: home

hero:
  name: Filexus
  text: Laravel File Attachments
  tagline: Production-ready file attachment system for Eloquent models
  image:
    src: /filexus.png
    alt: Filexus
  actions:
    - theme: brand
      text: Get Started
      link: /getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/filexus/filexus

features:
  - icon: ✨
    title: Simple, Fluent API
    details: Attach files to any Eloquent model with an elegant, easy-to-use API
  - icon: 📁
    title: Named Collections
    details: Organize files into named collections like avatar, gallery, or documents
  - icon: 🔢
    title: Single or Multiple Files
    details: Configure collections to accept single files or multiple files
  - icon: 🗑️
    title: Automatic Cleanup
    details: Files are automatically deleted when their parent model is removed
  - icon: ⏰
    title: File Expiration
    details: Set expiration dates on files for automatic cleanup
  - icon: 🧹
    title: Orphan Detection
    details: Detect and prune orphaned files that no longer have a parent model
  - icon: 🔐
    title: SHA256 Hashing
    details: Automatic hash generation for file deduplication and integrity checks
  - icon: 📊
    title: Rich Metadata
    details: Store and query comprehensive file metadata including MIME type, size, and more
  - icon: 🔍
    title: Query Scopes
    details: Powerful query scopes for efficient file lookups and filtering
  - icon: 🎨
    title: Event System
    details: Hook into file lifecycle with comprehensive event dispatching
  - icon: 🔑
    title: UUID/ULID Support
    details: Configure primary keys as auto-increment IDs, UUIDs, or ULIDs
  - icon: ✅
    title: 100% Test Coverage
    details: Thoroughly tested with Pest PHP for reliability and confidence
---

## Quick Example

```php
use Filexus\Traits\HasFiles;

class Post extends Model
{
    use HasFiles;
}

// Attach a file
$post->attach('thumbnail', $request->file('image'));

// Retrieve files
$thumbnail = $post->file('thumbnail');
$gallery = $post->files('gallery')->get();

// Replace a file
$post->replace('thumbnail', $newImage);

// Detach files
$post->detach('gallery', $fileId);
```

## Requirements

- PHP 8.2+
- Laravel 12+

## Installation

```bash
composer require filexus/filexus
```

[Get Started →](/getting-started)
