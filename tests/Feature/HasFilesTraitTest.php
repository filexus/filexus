<?php

declare(strict_types=1);

use Tests\Fixtures\Post;
use Tests\Fixtures\PostWithCustomCollections;
use Filexus\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('testing');
});

it('can attach a file to a model', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    $attachedFile = $post->attach('gallery', $file);

    expect($attachedFile)->toBeInstanceOf(File::class)
        ->and($attachedFile->collection)->toBe('gallery')
        ->and($attachedFile->original_name)->toBe('photo.jpg')
        ->and($attachedFile->fileable_id)->toBe($post->id)
        ->and($attachedFile->fileable_type)->toBe(Post::class);

    expect($post->files()->count())->toBe(1);
});

it('can attach multiple files to a model', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $files = [
        UploadedFile::fake()->image('photo1.jpg'),
        UploadedFile::fake()->image('photo2.jpg'),
        UploadedFile::fake()->image('photo3.jpg'),
    ];

    $attachedFiles = $post->attachMany('gallery', $files);

    expect($attachedFiles)->toHaveCount(3);
    expect($post->files()->count())->toBe(3);
});

it('can retrieve files from a collection', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file1 = UploadedFile::fake()->image('photo1.jpg');
    $file2 = UploadedFile::fake()->image('photo2.jpg');

    $post->attach('gallery', $file1);
    $post->attach('thumbnail', $file2);

    $galleryFiles = $post->getFiles('gallery');
    $thumbnailFile = $post->file('thumbnail');

    expect($galleryFiles)->toHaveCount(1)
        ->and($thumbnailFile)->toBeInstanceOf(File::class)
        ->and($thumbnailFile->collection)->toBe('thumbnail');
});

it('can replace a file in a collection', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $oldFile = UploadedFile::fake()->image('old.jpg');
    $newFile = UploadedFile::fake()->image('new.jpg');

    $post->attach('thumbnail', $oldFile);
    $post->replace('thumbnail', $newFile);

    expect($post->files()->count())->toBe(1);

    $currentFile = $post->file('thumbnail');
    expect($currentFile->original_name)->toBe('new.jpg');
});

it('can detach a file from a collection', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');
    $attachedFile = $post->attach('gallery', $file);

    expect($post->files()->count())->toBe(1);

    $post->detach('gallery', $attachedFile->id);

    expect($post->files()->count())->toBe(0);
});

it('throws exception for single file collection when attaching multiple', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file1 = UploadedFile::fake()->image('photo1.jpg');
    $file2 = UploadedFile::fake()->image('photo2.jpg');

    $post->attach('thumbnail', $file1);

    expect(fn() => $post->attach('thumbnail', $file2))
        ->toThrow(\Filexus\Exceptions\InvalidCollectionException::class);
});

it('stores file metadata correctly', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(500);
    $attachedFile = $post->attach('gallery', $file);

    expect($attachedFile->mime)->toContain('image')
        ->and($attachedFile->extension)->toBe('jpg')
        ->and($attachedFile->size)->toBeGreaterThan(0)
        ->and($attachedFile->hash)->toHaveLength(64); // SHA256 hash
});

it('can check if model has files', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    expect($post->hasFiles())->toBeFalse();

    $file = UploadedFile::fake()->image('photo.jpg');
    $post->attach('gallery', $file);

    expect($post->hasFiles())->toBeTrue()
        ->and($post->hasFile('gallery'))->toBeTrue()
        ->and($post->hasFile('thumbnail'))->toBeFalse();
});

it('deletes files when model is deleted', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');
    $attachedFile = $post->attach('gallery', $file);

    expect(File::count())->toBe(1);

    $post->delete();

    expect(File::count())->toBe(0);
});

it('can detach all files from a collection', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $post->attach('gallery', UploadedFile::fake()->image('photo1.jpg'));
    $post->attach('gallery', UploadedFile::fake()->image('photo2.jpg'));
    $post->attach('gallery', UploadedFile::fake()->image('photo3.jpg'));
    $post->attach('thumbnail', UploadedFile::fake()->image('thumb.jpg'));

    expect($post->files()->count())->toBe(4);

    $count = $post->detachAll('gallery');

    expect($count)->toBe(3);
    expect($post->files()->count())->toBe(1);
    expect($post->file('thumbnail'))->not->toBeNull();
});

it('throws exception when detaching non-existent file', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    expect(fn() => $post->detach('gallery', 99999))
        ->toThrow(\Filexus\Exceptions\FileNotFoundException::class);
});

it('can use model-specific collection configuration', function () {
    $post = PostWithCustomCollections::create(['title' => 'Test', 'content' => 'Content']);

    $file1 = UploadedFile::fake()->image('avatar.jpg');
    $post->attach('avatar', $file1);

    // Should throw exception because avatar is single-file collection
    expect(fn() => $post->attach('avatar', UploadedFile::fake()->image('avatar2.jpg')))
        ->toThrow(\Filexus\Exceptions\InvalidCollectionException::class);
});

it('falls back to global collection configuration', function () {
    config(['filexus.collections.custom_collection' => [
        'multiple' => false,
        'max_file_size' => 5120,
    ]]);

    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $post->attach('custom_collection', UploadedFile::fake()->image('file1.jpg'));

    expect(fn() => $post->attach('custom_collection', UploadedFile::fake()->image('file2.jpg')))
        ->toThrow(\Filexus\Exceptions\InvalidCollectionException::class);
});

it('uses default configuration when collection not defined', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $post->attach('undefined_collection', UploadedFile::fake()->image('file1.jpg'));
    $post->attach('undefined_collection', UploadedFile::fake()->image('file2.jpg'));

    expect($post->getFiles('undefined_collection'))->toHaveCount(2);
});

it('can replace multiple files in a collection', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $post->attach('gallery', UploadedFile::fake()->image('old1.jpg'));
    $post->attach('gallery', UploadedFile::fake()->image('old2.jpg'));

    expect($post->getFiles('gallery'))->toHaveCount(2);

    $post->replace('gallery', UploadedFile::fake()->image('new.jpg'));

    $files = $post->getFiles('gallery');
    expect($files)->toHaveCount(1);
    expect($files->first()->original_name)->toBe('new.jpg');
});

it('returns null when getting file from empty collection', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $file = $post->file('nonexistent_collection');

    expect($file)->toBeNull();
});

it('dispatches events when detaching files', function () {
    \Illuminate\Support\Facades\Event::fake();

    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    $post->detach('gallery', $file->id);

    \Illuminate\Support\Facades\Event::assertDispatched(\Filexus\Events\FileDeleting::class);
    \Illuminate\Support\Facades\Event::assertDispatched(\Filexus\Events\FileDeleted::class);
});

it('only deletes files when force deleting soft-deletable models', function () {
    // Create a model WITH soft deletes
    $post = Tests\Fixtures\SoftDeletablePost::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));

    // Soft delete should NOT delete files (because of isForceDeleting check)
    $post->delete();

    expect(File::count())->toBe(1);

    // Restore and force delete SHOULD delete files
    $post->restore();
    $post->forceDelete();

    expect(File::count())->toBe(0);
});

it('can attach many files to collection that allows multiple', function () {
    $post = PostWithCustomCollections::create(['title' => 'Test', 'content' => 'Content']);

    // Documents collection allows multiple files - test with multiple iterations
    $files = [
        UploadedFile::fake()->image('doc1.jpg'),
        UploadedFile::fake()->image('doc2.jpg'),
        UploadedFile::fake()->image('doc3.jpg'),
    ];

    $attachedFiles = $post->attachMany('documents', $files);

    expect($attachedFiles)->toHaveCount(3);
    expect($post->files('documents')->count())->toBe(3);

    // Verify each file was uploaded through the foreach loop
    $count = 0;
    foreach ($attachedFiles as $index => $file) {
        expect($file)->toBeInstanceOf(File::class);
        expect($file->collection)->toBe('documents');
        expect($file->fileable_id)->toBe($post->id);
        $count++;
    }

    // Ensure all files were iterated
    expect($count)->toBe(3);
});

it('attachMany with single file still uses foreach loop', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    // Test with single file to ensure foreach executes
    $files = [
        UploadedFile::fake()->image('single.jpg'),
    ];

    $attachedFiles = $post->attachMany('gallery', $files);

    expect($attachedFiles)->toHaveCount(1);
    expect($attachedFiles->first())->toBeInstanceOf(File::class);
});

it('throws exception when calling attachMany on single-file collection', function () {
    $post = PostWithCustomCollections::create(['title' => 'Test', 'content' => 'Content']);

    // Avatar is a single-file collection (multiple => false)
    $files = [
        UploadedFile::fake()->image('avatar1.jpg'),
        UploadedFile::fake()->image('avatar2.jpg'),
    ];

    // Should throw InvalidCollectionException because avatar doesn't allow multiple files
    expect(fn() => $post->attachMany('avatar', $files))
        ->toThrow(\Filexus\Exceptions\InvalidCollectionException::class);
});
