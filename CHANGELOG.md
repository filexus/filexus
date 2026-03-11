# Changelog

All notable changes to `filexus` will be documented in this file.

## [Unreleased]

### Fixed
- Fixed GitHub Actions test workflow compatibility with Laravel 11 by installing correct orchestra/testbench version
- Updated composer.json to support both Laravel 11 and 12 (`^11.0|^12.0`)

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
