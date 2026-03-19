# Changelog

All notable changes to `filexus` will be documented in this file.

## [Unreleased]

### Added
- Support for Laravel 13: updated `illuminate/*` constraints to include `^13.0`.

### Changed
- Bumped minimum PHP requirement to `^8.3`.
- Added support for Pest v4 and Pest Plugin Laravel v4 (required for Laravel 13).
- Updated `orchestra/testbench`, `pestphp/pest`, and `pestphp/pest-plugin-laravel` to support multiple versions for Laravel 11-13 compatibility.

### Notes
- This release may require PHP 8.3; ensure your environment is updated before upgrading.

## [1.0.4] - 2026-03-12

### Added
- Options parameter support for file upload methods:
  - `attach()` now accepts optional third parameter for upload options
  - `attachMany()` now accepts optional third parameter for upload options
  - `replace()` now accepts optional third parameter for upload options
  - Current option: `expires_at` - Set expiration date during upload
  - Example: `$file = $post->attach('temp', $upload, ['expires_at' => now()->addDays(7)])`
- Deduplication helper methods on File model:
  - `reference_count` - Accessor to get number of File records with same hash
  - `isLastReference()` - Check if this is the last File record referencing a physical file
  - Useful for optimizing storage and understanding deduplication impact

### Changed
- Improved file expiration workflow - expiration can now be set during upload instead of requiring separate save
- Enhanced documentation for file expiration with clearer examples

## [1.0.3] - 2026-03-11

### Added
- N+1 query prevention methods:
  - `fileFromLoaded()` - Get single file from collection using eager-loaded relationships
  - `getFilesFromLoaded()` - Get multiple files from collection using eager-loaded relationships
  - Both methods prevent N+1 queries when working with multiple models
  - Comprehensive documentation in [Avoiding N+1 Queries guide](docs/usage/avoiding-n-plus-one.md)
  - Documented important edge case: constrained eager loading only loads specified collections
- Test coverage for N+1 prevention methods

## [1.0.2] - 2026-03-11

### Fixed
- Fixed Intervention Image v3 API compatibility in ThumbnailGenerator
  - Fixed `encode()` method to properly require `EncoderInterface` parameter
  - Added `getEncoder()` method to return appropriate encoder based on file extension
  - Changed intervention/image requirement from `^2.7|^3.0` to `^3.0` for API consistency
  - Fixed test failures on prefer-lowest builds (intervention/image v2.7 incompatibility)

### Added
- Extended image format support in thumbnail generation:
  - AVIF format support (`AvifEncoder`)
  - BMP format support (`BmpEncoder`)
  - Existing formats: JPEG, PNG, GIF, WebP

### Changed
- Updated PHPStan from `^1.10` to `1.12.33` for PHP 8.4 compatibility
- Dropped Intervention Image v2 support (now requires v3.0+)

## [1.0.1] - 2026-03-11

### Added
- File deduplication feature based on SHA-256 hash
  - Automatically reuses physical files with identical content
  - Saves storage space by preventing duplicate file storage
  - Reference counting ensures files are only deleted when all references are removed
  - Configurable via `deduplicate` config option
- Automatic thumbnail generation for image uploads
  - Multiple thumbnail sizes configurable via `thumbnail_sizes`
  - Requires `intervention/image` package (v2.7+ or v3.0+)
  - Gracefully degrades if intervention/image is not installed
  - Automatic thumbnail cleanup when parent file is deleted
  - Configurable via `generate_thumbnails` config option
- Added `intervention/image` (v3.11.7) as dev dependency for testing
- New File model helper methods:
  - `thumbnailUrls()` - Get all thumbnail URLs
  - `thumbnailUrl($size)` - Get specific thumbnail URL
  - `hasThumbnails()` - Check if file has thumbnails
- Comprehensive test coverage for deduplication and thumbnail features
- Documentation for deduplication feature at `/advanced/deduplication`
- Documentation for thumbnail generation at `/advanced/thumbnails`

### Fixed
- Fixed "Cannot instantiate abstract class Model" error in FilexusServiceProvider
  - Changed from `Model::morphUsingUuids()` to correct `Builder::morphUsingUuids()`
- Fixed installation documentation verification example to actually test Filexus functionality
- Added code coverage ignore comments for untestable edge cases
- Fixed GitHub Actions test workflow compatibility with Laravel 11
- Updated composer.json to support both Laravel 11 and 12 (`^11.0|^12.0`)
- Fixed ULID tests compatibility with Laravel 11 prefer-lowest dependencies

### Changed
- Improved test suite with parallel execution (`--parallel` flag)
- Achieved 100% test coverage (150 tests, 378 assertions)
- Updated Intervention Image usage to v3 API:
  - `ImageManager::gd()` / `ImageManager::imagick()` factory methods
  - `read()` instead of `make()`
  - `cover()` instead of `fit()`

## [1.0.0] - 2026-03-11

### Added
- Initial release
- File attachment system for Eloquent models
- HasFiles trait for models
- Named file collections
- Single and multi-file collection support
- File upload, attach, replace, and detach operations
- Automatic file cleanup on model deletion
- File expiration support
- Orphan file detection and pruning
- Prune command for cleaning up files
- Events: FileUploading, FileUploaded, FileDeleting, FileDeleted
- SHA256 hash generation
- Comprehensive metadata storage
- Configuration with collection-specific rules
- Service provider with auto-discovery
- Pest test suite
- Complete VitePress documentation site (21 pages)
- GitHub Pages deployment workflow via GitHub Actions
- Documentation sections:
  - Getting Started (Installation, Quick Start)
  - Configuration (Global, Primary Keys, Per-Model, Custom Paths)
  - Usage (Basic Operations, Collections, Metadata, Expiration, Pruning)
  - Advanced (Events, FilexusManager, Query Scopes, File Deduplication)
  - API Reference (Trait Methods, File Model, Manager, Events, Exceptions)
- Development workflow guidelines in copilot instructions
- Comprehensive code examples throughout documentation
- VitePress logo and branding
