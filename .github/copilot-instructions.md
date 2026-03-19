# Filexus Package Development Instructions

## Package Overview

**Package Name:** filexus/filexus
**Namespace:** Filexus
**Purpose:** Production-ready Laravel file attachment system for Eloquent models
**Target:** Laravel 11+, PHP 8.3+

## Code Quality Standards

### PHP Standards

- Follow PSR-12 coding standards strictly
- Use strict types: `declare(strict_types=1);` in all PHP files
- Type hint everything: parameters, return types, properties
- Use PHP 8.3+ features: readonly properties, constructor property promotion, enums where appropriate
- Never use `mixed` type unless absolutely necessary

### Documentation

- Add PHPDoc blocks to all methods, classes, and properties
- Document all parameters with `@param`, return values with `@return`, exceptions with `@throws`
- Include usage examples in class-level PHPDoc when helpful
- Keep comments concise but informative

### Laravel Conventions

- Use Laravel naming conventions:
    - Controllers: PascalCase with `Controller` suffix
    - Models: Singular PascalCase
    - Traits: PascalCase with descriptive name
    - Commands: Kebab case (e.g., `filexus:prune`)
- Use service containers and dependency injection
- Use Laravel's helper functions where appropriate: `config()`, `storage_path()`, etc.
- Follow Laravel's directory structure conventions

## Architecture Principles

### Clean Architecture

- Separate concerns into distinct layers
- Services handle business logic
- Models handle data and relationships only
- Traits provide reusable functionality
- Commands orchestrate operations
- Events/Listeners for cross-cutting concerns

### Dependency Injection

- Always inject dependencies via constructor
- Never use facades in classes where DI is possible
- Use interfaces for flexibility and testability
- Services should be bound in the service provider

### Extensibility

- Use events for extensibility points
- Allow configuration overrides
- Make services swappable via interfaces
- Support custom implementations

## File Organization

```
src/
├── Commands/          # Artisan commands
├── Events/            # Domain events
├── Exceptions/        # Custom exceptions
├── Models/            # Eloquent models
├── Services/          # Business logic services
├── Traits/            # Reusable traits
├── FilexusManager.php # Main facade/manager class
└── FilexusServiceProvider.php
```

## Core Components

### Trait: HasFiles

- **Purpose:** Add file attachment capability to any Eloquent model
- **Methods:**
    - `files(?string $collection)` - Get files query builder
    - `file(string $collection)` - Get single file from collection
    - `attach(string $collection, UploadedFile $file)` - Attach file
    - `attachMany(string $collection, array $files)` - Attach multiple files
    - `replace(string $collection, UploadedFile $file)` - Replace file in collection
    - `detach(string $collection, int $fileId)` - Remove file

### Model: File

- **Table:** `files`
- **Relationships:** `morphTo('fileable')`
- **Fields:** id, disk, path, collection, fileable_type, fileable_id, original_name, mime, extension, size, hash, metadata (json), expires_at, timestamps
- **Casts:** metadata as array, expires_at as datetime
- **Scopes:** Add useful query scopes (whereCollection, whereExpired, etc.)

### Services

#### FileUploader

- Handle file upload process
- Generate UUID-based filenames
- Store files using Laravel Storage
- Calculate SHA256 hash
- Extract metadata (mime, size, extension)
- Return File model instance

#### FilePathGenerator

- Generate consistent storage paths
- Pattern: `/{model}/{id}/{collection}/{uuid}.{ext}`
- Support custom path generators via config

#### FilePruner

- Find and delete expired files
- Find and delete orphaned files (fileable no longer exists)
- Configurable grace period
- Batch processing for performance

#### FilexusManager

- Central facade for package operations
- Handle temporary uploads
- Manage file deduplication
- Coordinate services

### Commands

#### PruneCommand

- Signature: `filexus:prune`
- Delete expired files
- Delete orphans based on config (default: 24 hours)
- Output statistics
- Scheduler-friendly

### Events

- `FileUploading` - Before file upload
- `FileUploaded` - After successful upload
- `FileDeleting` - Before file deletion
- `FileDeleted` - After file deletion

## Configuration Design

### config/filexus.php

```php
return [
    'default_disk' => env('FILEXUS_DISK', 'public'),

    'collections' => [
        'default' => [
            'multiple' => true,
            'max_file_size' => 10240, // KB
            'allowed_mimes' => [],
        ],
    ],

    'orphan_cleanup_hours' => 24,
    'max_file_size' => 10240, // KB, global default
    'allowed_mimes' => [], // Empty = allow all

    'path_generator' => \Filexus\Services\FilePathGenerator::class,
];
```

## Database Schema

### Migration: create_files_table

- Columns as specified in requirements
- Indexes:
    - `index(['fileable_type', 'fileable_id'])`
    - `index('collection')`
    - `index('hash')`
    - `index('expires_at')`
- Foreign key handling: use morphs without constraints

## Testing Guidelines

### Use Pest PHP

- Write feature tests for trait methods
- Write unit tests for services
- Test edge cases: single vs multiple collections, replacements, deletions
- Use Laravel's fake Storage
- Test events are dispatched
- Test validation and exceptions

### Test Structure

```php
it('attaches file to collection', function () {
    // Arrange
    // Act
    // Assert
});
```

## Development Workflow

**IMPORTANT:** Follow this workflow for every bug fix or new feature to maintain code quality and completeness.

### Step-by-Step Process

#### 1. Implement the Feature/Fix

- Write clean, well-structured code following PSR-12
- Use strict types and proper type hints
- Follow SOLID principles and clean architecture
- Inject dependencies via constructor
- Add PHPDoc blocks to all methods and classes

#### 2. Write Comprehensive Tests

- Write tests **immediately** after implementation (not later!)
- Use Pest PHP test framework
- Cover both happy path and edge cases
- Write unit tests for services/helpers
- Write feature tests for trait methods and end-to-end flows
- Test validation rules and exception handling
- Test event dispatching
- Use Laravel's `Storage::fake()` for file operations

```php
// Example test
it('attaches file to single-file collection', function () {
    Storage::fake('public');
    $post = Post::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg');

    $attachment = $post->attach('thumbnail', $file);

    expect($attachment)->toBeInstanceOf(File::class);
    expect($post->files('thumbnail')->count())->toBe(1);
    Storage::disk('public')->assertExists($attachment->path);
});
```

#### 3. Verify Test Coverage

Run tests and ensure 100% coverage:

```bash
# Run all tests
composer test

# Run with coverage report
composer test-coverage

# Check specific coverage
./vendor/bin/pest --coverage --min=100
```

**Requirements:**

- All new code must have 100% test coverage
- All existing tests must pass
- No regressions allowed

#### 4. Run Static Analysis

Ensure code quality with PHPStan:

```bash
composer analyse
```

Fix all errors and warnings before proceeding.

#### 5. Format Code

Run PHP-CS-Fixer to ensure consistent style:

```bash
composer format

# Or check without fixing
composer format-check
```

#### 6. Update Documentation

Update the VitePress documentation in `docs/`:

**a. Determine Affected Pages:**

- New feature → Add to relevant guide (usage/, advanced/, or api/)
- Modified API → Update API reference (api/trait-methods.md, api/file-model.md, api/manager.md)
- Configuration change → Update configuration docs (configuration/\*.md)
- Breaking change → Update getting-started.md and add migration guide

**b. Add Code Examples:**

- Include practical, copy-paste-ready examples
- Show both basic and advanced usage
- Add Blade template examples where relevant
- Include controller examples for common patterns

**c. Update These Documentation Sections:**

- **Usage Guide**: Add examples showing how to use the feature
- **API Reference**: Document new methods, parameters, return types
- **Configuration**: Document any new config options
- **Migration Guide**: If breaking changes, document upgrade path

**Example Documentation Locations:**

- Trait methods → `docs/api/trait-methods.md`
- File model properties → `docs/api/file-model.md`
- Manager methods → `docs/api/manager.md`
- Events → `docs/advanced/events.md`
- Basic operations → `docs/usage/basic.md`
- Collections → `docs/usage/collections.md`

**d. Build and Verify Docs:**

```bash
npm run docs:dev  # Preview at http://localhost:5173/filexus/
npm run docs:build  # Verify build succeeds
```

#### 7. Update CHANGELOG.md

Add entry under "Unreleased" section:

```markdown
## [Unreleased]

### Added

- New feature description (#PR number)

### Changed

- Modified behavior description (#PR number)

### Fixed

- Bug fix description (#PR number)

### Breaking Changes

- Breaking change description with migration instructions (#PR number)
```

#### 8. Review Checklist

Before committing, verify:

- [ ] Feature/fix implemented and working
- [ ] All tests written and passing
- [ ] 100% test coverage achieved
- [ ] PHPStan analysis passes with no errors
- [ ] Code formatted with PHP-CS-Fixer
- [ ] Documentation updated in VitePress docs
- [ ] Documentation builds without errors
- [ ] CHANGELOG.md updated
- [ ] No breaking changes (or documented if unavoidable)
- [ ] Git commit message is clear and descriptive

### Quick Commands Summary

```bash
# Complete workflow in one go
composer test-coverage && \
composer analyse && \
composer format && \
npm run docs:build && \
echo "✅ All checks passed!"
```

### Breaking Changes Policy

If introducing a breaking change:

1. **Document thoroughly** in CHANGELOG.md under "Breaking Changes"
2. **Update migration guide** in documentation
3. **Bump major version** when releasing (if following semver)
4. **Provide code examples** showing before/after
5. **Consider deprecation** instead of immediate removal

### When to Skip Steps

**Never skip:** Tests, test coverage, documentation

**Can skip if not applicable:**

- CHANGELOG.md (for internal refactoring with no public API changes)
- Documentation updates (for internal-only changes)

## Naming Conventions

### Variables

- Use descriptive camelCase: `$uploadedFile`, `$collectionName`
- Avoid abbreviations unless common: `$mime` is OK, `$fl` is not

### Methods

- Verb-based: `attachFile()`, `getFiles()`, `deleteExpired()`
- Boolean methods: `isMultiple()`, `hasFiles()`

### Classes

- Descriptive nouns: `FileUploader`, `FilePruner`
- Services end with purpose: `FilePathGenerator`

## Error Handling

- Create custom exceptions in `Exceptions/` directory
- Exception names: `InvalidCollectionException`, `FileUploadException`
- Provide helpful error messages
- Don't catch exceptions unless you can handle them meaningfully

## Usage Examples to Support

### Basic Attachment

```php
use Filexus\Traits\HasFiles;

class Post extends Model
{
    use HasFiles;
}

$post->attach('thumbnail', $request->file('image'));
```

### Multiple Files

```php
$post->attachMany('gallery', $request->file('images'));
```

### Retrieve Files

```php
$thumbnail = $post->file('thumbnail');
$gallery = $post->files('gallery')->get();
$allFiles = $post->files()->get();
```

### Replace File

```php
$post->replace('thumbnail', $newFile);
```

### Detach File

```php
$post->detach('gallery', $fileId);
```

### Collection Config

```php
// In model or config
protected $fileCollections = [
    'avatar' => ['multiple' => false],
    'documents' => ['multiple' => true],
];
```

## Package Installation Flow

1. `composer require filexus/filexus`
2. `php artisan vendor:publish --tag=filexus-config`
3. `php artisan vendor:publish --tag=filexus-migrations`
4. `php artisan migrate`
5. Add trait to model
6. Start attaching files

## Advanced Features (If Implemented)

### Temporary Uploads

```php
$tempFile = Filexus::temporaryUpload($file);
// Later...
$post->attachExisting('thumbnail', $tempFile);
```

### File Deduplication

- Check hash before storing
- Reuse existing file if hash matches
- Reference count for shared files

### Signed URLs

```php
$file->temporaryUrl(now()->addHours(1));
```

### Query Helpers

```php
Post::whereHasFile('thumbnail')->get();
User::whereHasFiles()->get();
```

## Important Reminders

- Always validate collection configuration before operations
- Handle single-file collections by auto-deleting previous file
- Clean up physical files when File records are deleted
- Use database transactions where appropriate
- Validate file types and sizes based on config
- Generate unique filenames to prevent collisions
- Store original filename for downloads
- Handle file storage failures gracefully

## Service Provider Registration

- Register config: `mergeConfigFrom()` and `publishes()`
- Register migrations: `loadMigrationsFrom()` and `publishes()`
- Register commands: `commands()`
- Bind services: singleton for manager, contextual bindings for services
- Boot observers or listeners if needed
