# Changelog

All notable changes to `filexus` will be documented in this file.

## [Unreleased]

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
