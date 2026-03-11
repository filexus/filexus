<?php

declare(strict_types=1);

use Filexus\FilexusManager;
use Filexus\Services\FileUploader;
use Filexus\Services\FilePruner;
use Filexus\Models\File;
use Tests\Fixtures\Post;
use Tests\Fixtures\DummyModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('testing');
});

it('can upload a file through manager', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->image('test.jpg');

    $manager = app(FilexusManager::class);
    $uploadedFile = $manager->upload($post, 'gallery', $file);

    expect($uploadedFile)->toBeInstanceOf(File::class);
    expect($uploadedFile->original_name)->toBe('test.jpg');
    expect($uploadedFile->collection)->toBe('gallery');
});

it('can upload with custom config', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->image('test.jpg');

    $manager = app(FilexusManager::class);
    $uploadedFile = $manager->upload($post, 'gallery', $file, ['max_file_size' => 20480]);

    expect($uploadedFile)->toBeInstanceOf(File::class);
});

it('can prune expired files through manager', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $expiredFile = $post->attach('gallery', UploadedFile::fake()->image('expired.jpg'));
    $expiredFile->expires_at = now()->subDay();
    $expiredFile->save();

    $manager = app(FilexusManager::class);
    $count = $manager->pruneExpired();

    expect($count)->toBe(1);
    expect(File::count())->toBe(0);
});

it('can prune orphaned files through manager', function () {
    // Create orphaned file by referencing a model ID that doesn't exist
    $orphanedFile = new File([
        'disk' => 'testing',
        'path' => 'test/orphaned.jpg',
        'collection' => 'gallery',
        'fileable_type' => DummyModel::class,
        'fileable_id' => 99999,
        'original_name' => 'test.jpg',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024,
        'hash' => 'test_hash',
    ]);
    $orphanedFile->created_at = now()->subHours(25);
    $orphanedFile->save();

    $manager = app(FilexusManager::class);
    $count = $manager->pruneOrphaned(24);

    expect($count)->toBe(1);
    expect(File::count())->toBe(0);
});

it('can prune orphaned files with default hours', function () {
    // Create orphaned file by referencing a model ID that doesn't exist
    $orphanedFile = new File([
        'disk' => 'testing',
        'path' => 'test/orphaned.jpg',
        'collection' => 'gallery',
        'fileable_type' => DummyModel::class,
        'fileable_id' => 99999,
        'original_name' => 'test.jpg',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024,
        'hash' => 'test_hash',
    ]);
    $orphanedFile->created_at = now()->subHours(25);
    $orphanedFile->save();

    $manager = app(FilexusManager::class);
    $count = $manager->pruneOrphaned();

    expect($count)->toBe(1);
});

it('can prune all files and get statistics', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    // Create expired file
    $expiredFile = $post->attach('gallery', UploadedFile::fake()->image('expired.jpg'));
    $expiredFile->expires_at = now()->subDay();
    $expiredFile->save();

    // Create orphaned files by referencing model IDs that don't exist
    $orphanedFile = new File([
        'disk' => 'testing',
        'path' => 'test/orphaned1.jpg',
        'collection' => 'gallery',
        'fileable_type' => DummyModel::class,
        'fileable_id' => 99998,
        'original_name' => 'orphaned.jpg',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024,
        'hash' => 'test_hash_1',
    ]);
    $orphanedFile->created_at = now()->subHours(25);
    $orphanedFile->save();

    $orphanedFile2 = new File([
        'disk' => 'testing',
        'path' => 'test/orphaned2.jpg',
        'collection' => 'gallery',
        'fileable_type' => DummyModel::class,
        'fileable_id' => 99999,
        'original_name' => 'orphaned2.jpg',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024,
        'hash' => 'test_hash_2',
    ]);
    $orphanedFile2->created_at = now()->subHours(30);
    $orphanedFile2->save();

    $manager = app(FilexusManager::class);
    $result = $manager->pruneAll();

    expect($result)->toHaveKeys(['expired', 'orphaned', 'total']);
    expect($result['expired'])->toBe(1);
    expect($result['orphaned'])->toBe(2);
    expect($result['total'])->toBe(3);
    expect(File::count())->toBe(0);
});

it('can get prune statistics', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $expiredFile = $post->attach('gallery', UploadedFile::fake()->image('expired.jpg'));
    $expiredFile->expires_at = now()->subDay();
    $expiredFile->save();

    $oldFile = $post->attach('gallery', UploadedFile::fake()->image('old.jpg'));
    $oldFile->created_at = now()->subHours(25);
    $oldFile->save();

    $manager = app(FilexusManager::class);
    $stats = $manager->getPruneStatistics();

    expect($stats)->toHaveKeys(['expired', 'potentially_orphaned', 'total']);
    expect($stats['expired'])->toBe(1);
    expect($stats['potentially_orphaned'])->toBe(1);
});

it('can find files by hash', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    $manager = app(FilexusManager::class);
    $found = $manager->findByHash($file->hash);

    expect($found)->toBeInstanceOf(File::class);
    expect($found->id)->toBe($file->id);
});

it('returns null when file with hash not found', function () {
    $manager = app(FilexusManager::class);
    $found = $manager->findByHash('nonexistent_hash');

    expect($found)->toBeNull();
});

it('can check if hash exists', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    $manager = app(FilexusManager::class);
    $exists = $manager->hashExists($file->hash);

    expect($exists)->toBeTrue();
});

it('returns false when hash does not exist', function () {
    $manager = app(FilexusManager::class);
    $exists = $manager->hashExists('nonexistent_hash');

    expect($exists)->toBeFalse();
});
