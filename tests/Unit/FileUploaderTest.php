<?php

declare(strict_types=1);

use Filexus\Services\FileUploader;
use Filexus\Exceptions\FileUploadException;
use Tests\Fixtures\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Filexus\Events\FileUploading;
use Filexus\Events\FileUploaded;

beforeEach(function () {
    Storage::fake('testing');
    Event::fake();
});

it('throws exception for invalid file', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $invalidFile = \Mockery::mock(UploadedFile::class);
    $invalidFile->shouldReceive('isValid')->andReturn(false);

    $uploader = app(FileUploader::class);

    expect(fn() => $uploader->upload($post, 'gallery', $invalidFile))
        ->toThrow(FileUploadException::class, 'File upload was not successful');
});

it('throws exception when file exceeds max size', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $largeFile = UploadedFile::fake()->create('large.pdf', 20000); // 20MB

    $uploader = app(FileUploader::class);
    $config = ['max_file_size' => 1024]; // 1MB max

    expect(fn() => $uploader->upload($post, 'gallery', $largeFile, $config))
        ->toThrow(FileUploadException::class);
});

it('throws exception for invalid mime type', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $uploader = app(FileUploader::class);
    $config = ['allowed_mimes' => ['image/jpeg', 'image/png']];

    expect(fn() => $uploader->upload($post, 'gallery', $pdfFile, $config))
        ->toThrow(FileUploadException::class);
});

it('allows files with valid mime types', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $imageFile = UploadedFile::fake()->image('photo.jpg');

    $uploader = app(FileUploader::class);
    $config = ['allowed_mimes' => ['image/jpeg', 'image/png']];

    $file = $uploader->upload($post, 'gallery', $imageFile, $config);

    expect($file)->not->toBeNull();
    expect($file->mime)->toContain('image');
});

it('dispatches uploading and uploaded events', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->image('test.jpg');

    $uploader = app(FileUploader::class);
    $uploader->upload($post, 'gallery', $file);

    Event::assertDispatched(FileUploading::class);
    Event::assertDispatched(FileUploaded::class);
});

it('uses config from global settings when not provided', function () {
    config(['filexus.max_file_size' => 2048]);
    config(['filexus.allowed_mimes' => []]);

    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->image('test.jpg');

    $uploader = app(FileUploader::class);
    $result = $uploader->upload($post, 'gallery', $file);

    expect($result)->not->toBeNull();
});

it('throws exception when storage fails', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->image('test.jpg');

    // Mock Storage to fail
    Storage::shouldReceive('disk')->andReturnSelf();
    Storage::shouldReceive('putFileAs')->andReturn(false);

    $uploader = app(FileUploader::class);

    expect(fn() => $uploader->upload($post, 'gallery', $file))
        ->toThrow(FileUploadException::class, 'Could not store file to disk');
});

it('calculates file hash correctly', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->image('test.jpg');

    $uploader = app(FileUploader::class);
    $uploadedFile = $uploader->upload($post, 'gallery', $file);

    expect($uploadedFile->hash)->toHaveLength(64); // SHA256 hash
    expect($uploadedFile->hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('handles files without extension', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->create('noextension', 100);

    $uploader = app(FileUploader::class);
    $uploadedFile = $uploader->upload($post, 'gallery', $file);

    expect($uploadedFile->original_name)->toBe('noextension');
    expect($uploadedFile->extension)->toBe('');
});

it('stores files with correct metadata', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = UploadedFile::fake()->image('test.jpg', 100, 200)->size(512);

    $uploader = app(FileUploader::class);
    $uploadedFile = $uploader->upload($post, 'gallery', $file);

    expect($uploadedFile->original_name)->toBe('test.jpg');
    expect($uploadedFile->collection)->toBe('gallery');
    expect($uploadedFile->fileable_type)->toBe(Post::class);
    expect($uploadedFile->fileable_id)->toBe($post->id);
    expect($uploadedFile->size)->toBeGreaterThan(0);
    expect($uploadedFile->disk)->toBe('testing');
    expect($uploadedFile->metadata)->toBeArray();
});
