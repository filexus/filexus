<?php

declare(strict_types=1);

use Filexus\Models\File;
use Tests\Fixtures\Post;
use Tests\Fixtures\DummyModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('testing');
});

it('can get file url', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $uploadedFile = UploadedFile::fake()->image('photo.jpg');
    $file = $post->attach('gallery', $uploadedFile);

    $url = $file->url();

    expect($url)->toBeString()
        ->and($url)->toContain($file->path);
});

it('can check if file exists in storage', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $uploadedFile = UploadedFile::fake()->image('photo.jpg');
    $file = $post->attach('gallery', $uploadedFile);

    expect($file->exists())->toBeTrue();
});

it('can detect file types', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $imageFile = UploadedFile::fake()->image('photo.jpg');
    $file = $post->attach('gallery', $imageFile);

    expect($file->isImage())->toBeTrue()
        ->and($file->isVideo())->toBeFalse()
        ->and($file->isAudio())->toBeFalse()
        ->and($file->isPdf())->toBeFalse();
});

it('provides human readable file size', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg')->size(1024); // 1MB
    $attachedFile = $post->attach('gallery', $file);

    $size = $attachedFile->human_readable_size;

    expect($size)->toBeString()
        ->and($size)->toContain('KB');
});

it('can filter files by collection', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $post->attach('gallery', UploadedFile::fake()->image('photo1.jpg'));
    $post->attach('gallery', UploadedFile::fake()->image('photo2.jpg'));
    $post->attach('thumbnail', UploadedFile::fake()->image('thumb.jpg'));

    $galleryFiles = File::whereCollection('gallery')->get();
    $thumbnailFiles = File::whereCollection('thumbnail')->get();

    expect($galleryFiles)->toHaveCount(2)
        ->and($thumbnailFiles)->toHaveCount(1);
});

it('can get temporary url', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    $expiration = now()->addHour();
    $tempUrl = $file->temporaryUrl($expiration);

    expect($tempUrl)->toBeString();
});

it('can delete file from storage', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    expect($file->exists())->toBeTrue();

    $deleted = $file->deleteFromStorage();

    expect($deleted)->toBeTrue();
    expect($file->exists())->toBeFalse();
});

it('returns false when deleting non-existent file from storage', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    // Delete from storage first
    Storage::disk($file->disk)->delete($file->path);

    $result = $file->deleteFromStorage();

    expect($result)->toBeFalse();
});

it('can check if file is expired', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $expiredFile = $post->attach('gallery', UploadedFile::fake()->image('expired.jpg'));
    $expiredFile->expires_at = now()->subDay();
    $expiredFile->save();

    $activeFile = $post->attach('gallery', UploadedFile::fake()->image('active.jpg'));
    $activeFile->expires_at = now()->addDay();
    $activeFile->save();

    $noExpiryFile = $post->attach('gallery', UploadedFile::fake()->image('permanent.jpg'));

    expect($expiredFile->isExpired())->toBeTrue();
    expect($activeFile->isExpired())->toBeFalse();
    expect($noExpiryFile->isExpired())->toBeFalse();
});

it('can filter non-expired files', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $expiredFile = $post->attach('gallery', UploadedFile::fake()->image('expired.jpg'));
    $expiredFile->expires_at = now()->subDay();
    $expiredFile->save();

    $activeFile = $post->attach('gallery', UploadedFile::fake()->image('active.jpg'));
    $activeFile->expires_at = now()->addDay();
    $activeFile->save();

    $noExpiryFile = $post->attach('gallery', UploadedFile::fake()->image('permanent.jpg'));

    $nonExpiredFiles = File::whereNotExpired()->get();

    expect($nonExpiredFiles)->toHaveCount(2);
    expect($nonExpiredFiles->pluck('id')->toArray())->toContain($activeFile->id, $noExpiryFile->id);
});

it('can filter orphaned files', function () {
    $post1 = Post::create(['title' => 'Test 1', 'content' => 'Content']);
    $file1 = $post1->attach('gallery', UploadedFile::fake()->image('file1.jpg'));

    // Create orphaned file by referencing a model ID that doesn't exist
    $orphanedFile = new File([
        'disk' => 'testing',
        'path' => 'test/orphaned.jpg',
        'collection' => 'gallery',
        'fileable_type' => DummyModel::class,
        'fileable_id' => 99999,
        'original_name' => 'orphaned.jpg',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024,
        'hash' => 'test_hash',
    ]);
    $orphanedFile->save();

    $orphanedFiles = File::whereOrphaned()->get();

    expect($orphanedFiles)->toHaveCount(1);
    expect($orphanedFiles->first()->id)->toBe($orphanedFile->id);
});

it('detects video files correctly', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->create('video.mp4', 1000, 'video/mp4'));

    expect($file->isVideo())->toBeTrue();
    expect($file->isImage())->toBeFalse();
    expect($file->isAudio())->toBeFalse();
    expect($file->isPdf())->toBeFalse();
});

it('detects audio files correctly', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->create('audio.mp3', 1000, 'audio/mpeg'));

    expect($file->isAudio())->toBeTrue();
    expect($file->isImage())->toBeFalse();
    expect($file->isVideo())->toBeFalse();
    expect($file->isPdf())->toBeFalse();
});

it('detects pdf files correctly', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf'));

    expect($file->isPdf())->toBeTrue();
    expect($file->isImage())->toBeFalse();
    expect($file->isVideo())->toBeFalse();
    expect($file->isAudio())->toBeFalse();
});

it('calculates human readable size for different units', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    // Create a file record with a specific size (bypassing actual upload for size control)
    $file = new File([
        'disk' => 'testing',
        'path' => 'test/path.jpg',
        'collection' => 'gallery',
        'fileable_type' => get_class($post),
        'fileable_id' => $post->id,
        'original_name' => 'test.jpg',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024 * 1024 * 2.5, // 2.5 MB
        'hash' => 'test_hash',
    ]);
    $file->save();

    expect($file->human_readable_size)->toContain('MB');

    // Test GB
    $largeFile = new File([
        'disk' => 'testing',
        'path' => 'test/large.jpg',
        'collection' => 'gallery',
        'fileable_type' => get_class($post),
        'fileable_id' => $post->id,
        'original_name' => 'large.jpg',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024 * 1024 * 1024 * 1.5, // 1.5 GB
        'hash' => 'test_hash_2',
    ]);
    $largeFile->save();

    expect($largeFile->human_readable_size)->toContain('GB');
});

it('automatically deletes file from storage when model is deleted', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    $path = $file->path;
    $disk = $file->disk;

    expect(Storage::disk($disk)->exists($path))->toBeTrue();

    $file->delete();

    expect(Storage::disk($disk)->exists($path))->toBeFalse();
});

it('has fileable relationship', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    expect($file->fileable)->toBeInstanceOf(Post::class);
    expect($file->fileable->id)->toBe($post->id);
});
