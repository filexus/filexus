# Filexus

[![Latest Version on Packagist](https://img.shields.io/packagist/v/filexus/filexus.svg?style=flat-square)](https://packagist.org/packages/filexus/filexus)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/filexus/filexus/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/filexus/filexus/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/filexus/filexus.svg?style=flat-square)](https://packagist.org/packages/filexus/filexus)
[![Code Coverage](https://img.shields.io/codecov/c/github/filexus/filexus?style=flat-square)](https://codecov.io/gh/filexus/filexus)

A production-ready Laravel package that provides a simple and elegant file attachment system for Eloquent models. Attach files to any model with support for collections, single/multiple file modes, automatic cleanup, and more.

## Features

- ✨ Simple, fluent API for attaching files to models
- 📁 Named file collections (e.g., `avatar`, `gallery`, `documents`)
- 🔢 Single-file or multi-file collections
- 🗑️ Automatic file cleanup when models are deleted
- ⏰ File expiration support
- 🧹 Orphan file detection and pruning
- 🔐 SHA256 hash generation for deduplication
- 📊 Comprehensive metadata storage
- 🔍 Query scopes for efficient file lookups
- 🎨 Clean architecture with events and extensibility
- ✅ 100% test coverage with Pest PHP

## Requirements

- PHP 8.2+
- Laravel 12+

## Installation

Install the package via Composer:

```bash
composer require filexus/filexus
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filexus-config
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=filexus-migrations
php artisan migrate
```

## Quick Start

### 1. Add the Trait to Your Model

```php
use Illuminate\Database\Eloquent\Model;
use Filexus\Traits\HasFiles;

class Post extends Model
{
    use HasFiles;
}
```

### 2. Attach Files

```php
// Attach a single file
$post->attach('thumbnail', $request->file('image'));

// Attach multiple files
$post->attachMany('gallery', $request->file('images'));
```

### 3. Retrieve Files

```php
// Get a single file from a collection
$thumbnail = $post->file('thumbnail');

// Get all files from a collection
$gallery = $post->files('gallery')->get();
```

## Documentation

For complete documentation, including configuration options, advanced usage, API reference, and examples, visit:

**🔗 [https://filexus.github.io/filexus/](https://filexus.github.io/filexus/)**

The documentation includes:
- 📖 Getting Started Guide
- ⚙️ Configuration (global, primary keys, collections)
- 📝 Usage Examples (collections, metadata, expiration, pruning)
- 🔧 Advanced Topics (events, manager, scopes)
- 📚 API Reference (trait methods, File model, events)

## Testing

```bash
composer test              # Run test suite
composer test-coverage     # Run with coverage report
composer analyse           # Run static analysis
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email manlupigjohnmichael@gmail.com instead of using the issue tracker. Please see our [Security Policy](SECURITY.md) for more details.

## Credits

- [John Michael Manlupig](https://github.com/avidianity)
- [All Contributors](https://github.com/filexus/filexus/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
