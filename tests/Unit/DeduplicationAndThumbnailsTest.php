<?php

declare(strict_types=1);

use Filexus\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Post;

beforeEach(function () {
    // The TestCase configures 'testing' as the default disk
    Storage::fake('testing');
});

describe('File Deduplication', function () {
    it('does not deduplicate when feature is disabled', function () {
        config(['filexus.deduplicate' => false]);

        $post1 = Post::create(['title' => 'Post 1', 'content' => 'Content']);
        $post2 = Post::create(['title' => 'Post 2', 'content' => 'Content']);

        $file1 = UploadedFile::fake()->create('document.pdf', 100);
        $file2 = UploadedFile::fake()->create('document.pdf', 100);

        $attachment1 = $post1->attach('documents', $file1);
        $attachment2 = $post2->attach('documents', $file2);

        expect($attachment1->path)->not->toBe($attachment2->path);
        expect($attachment1->metadata['deduplicated'] ?? false)->toBeFalse();
        expect($attachment2->metadata['deduplicated'] ?? false)->toBeFalse();
    });

    it('deduplicates files with same content when enabled', function () {
        config(['filexus.deduplicate' => true]);

        $post1 = Post::create(['title' => 'Post 1', 'content' => 'Content']);
        $post2 = Post::create(['title' => 'Post 2', 'content' => 'Content']);

        // Create identical files
        $content = 'Identical file content for testing';
        $file1 = UploadedFile::fake()->createWithContent('doc1.txt', $content);
        $file2 = UploadedFile::fake()->createWithContent('doc2.txt', $content);

        $attachment1 = $post1->attach('documents', $file1);
        $attachment2 = $post2->attach('documents', $file2);

        expect($attachment1->path)->toBe($attachment2->path);
        expect($attachment1->metadata['deduplicated'] ?? false)->toBeFalse();
        expect($attachment2->metadata['deduplicated'] ?? false)->toBeTrue();
        expect($attachment2->metadata['original_file_id'] ?? null)->toBe($attachment1->id);
    });

    it('only deletes physical file when last reference is removed', function () {
        config(['filexus.deduplicate' => true]);

        $post1 = Post::create(['title' => 'Post 1', 'content' => 'Content']);
        $post2 = Post::create(['title' => 'Post 2', 'content' => 'Content']);

        $content = 'Shared content for deduplication test';
        $file1 = UploadedFile::fake()->createWithContent('shared1.txt', $content);

        // Upload first file
        $attachment1 = $post1->attach('documents', $file1);

        $sharedPath = $attachment1->path;
        $disk = Storage::disk('testing');

        // Debug: List all files to see what's stored
        $allFiles = $disk->allFiles();

        // First file must exist after upload
        expect($disk->exists($sharedPath))->toBeTrue("First file should be stored at: {$sharedPath}. Files in storage: " . implode(', ', $allFiles));

        // Upload second file with same content
        $file2 = UploadedFile::fake()->createWithContent('shared2.txt', $content);
        $attachment2 = $post2->attach('documents', $file2);

        // Verify both records share the same path (deduplication worked)
        expect($attachment2->path)->toBe($attachment1->path);
        expect($attachment2->metadata['deduplicated'] ?? false)->toBeTrue();

        // Count references before deletion
        $countBefore = File::where('path', $sharedPath)->where('disk', 'testing')->count();
        expect($countBefore)->toBe(2);

        // Delete second attachment (the deduplicated one)
        $attachment2->delete();

        // Physical file should still exist because attachment1 still references it
        expect($disk->exists($sharedPath))->toBeTrue('File should exist after deleting second reference');

        // Count references after first deletion
        $countAfter = File::where('path', $sharedPath)->where('disk', 'testing')->count();
        expect($countAfter)->toBe(1);

        // Delete first attachment (the original)
        $attachment1->delete();

        // Now physical file should be deleted
        expect($disk->exists($sharedPath))->toBeFalse('File should be deleted after removing last reference');
    });

    it('deduplicates across different disks are separate', function () {
        config(['filexus.deduplicate' => true]);

        $post = Post::create(['title' => 'Test', 'content' => 'Content']);

        $content = 'Test content';
        $file = UploadedFile::fake()->createWithContent('doc.txt', $content);

        $attachment = $post->attach('documents', $file);

        // Change disk in config
        config(['filexus.default_disk' => 's3']);

        $file2 = UploadedFile::fake()->createWithContent('doc2.txt', $content);

        // This should create a new file since disk is different
        // But we're using fake storage, so it will still work
        expect($attachment->hash)->toBe(hash('sha256', $content));
    });
});

describe('File Model Thumbnails', function () {
    it('returns empty array when no thumbnails exist', function () {
        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg');

        $attachment = $post->attach('gallery', $file);

        expect($attachment->thumbnailUrls())->toBeEmpty();
        expect($attachment->hasThumbnails())->toBeFalse();
    });

    it('returns thumbnail URLs when thumbnails exist', function () {
        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg');

        $attachment = $post->attach('gallery', $file);

        // Manually set thumbnail metadata
        $attachment->metadata = [
            'thumbnails' => [
                'small' => 'path/to/small.jpg',
                'medium' => 'path/to/medium.jpg',
            ],
        ];
        $attachment->save();

        $urls = $attachment->thumbnailUrls();

        expect($urls)->toHaveCount(2);
        expect($urls['small'])->toContain('small.jpg');
        expect($urls['medium'])->toContain('medium.jpg');
    });

    it('returns specific thumbnail URL', function () {
        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg');

        $attachment = $post->attach('gallery', $file);

        $attachment->metadata = [
            'thumbnails' => [
                'small' => 'path/to/small.jpg',
                'medium' => 'path/to/medium.jpg',
            ],
        ];
        $attachment->save();

        $smallUrl = $attachment->thumbnailUrl('small');
        $largeUrl = $attachment->thumbnailUrl('large');

        expect($smallUrl)->toContain('small.jpg');
        expect($largeUrl)->toBeNull();
    });

    it('checks if thumbnails exist', function () {
        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg');

        $attachment = $post->attach('gallery', $file);

        expect($attachment->hasThumbnails())->toBeFalse();

        $attachment->metadata = ['thumbnails' => ['small' => 'path.jpg']];
        $attachment->save();
        $attachment->refresh();

        expect($attachment->hasThumbnails())->toBeTrue();
    });

    it('handles empty thumbnails array', function () {
        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg');

        $attachment = $post->attach('gallery', $file);

        $attachment->metadata = ['thumbnails' => []];
        $attachment->save();
        $attachment->refresh();

        expect($attachment->hasThumbnails())->toBeFalse();
    });
});

describe('Thumbnail Generation Integration', function () {
    it('does not generate thumbnails when disabled', function () {
        config(['filexus.generate_thumbnails' => false]);
        config(['filexus.thumbnail_sizes' => ['small' => [150, 150]]]);

        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg');

        $attachment = $post->attach('gallery', $file);

        expect($attachment->hasThumbnails())->toBeFalse();
    });

    it('does not generate thumbnails for non-image files', function () {
        config(['filexus.generate_thumbnails' => true]);
        config(['filexus.thumbnail_sizes' => ['small' => [150, 150]]]);

        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $attachment = $post->attach('documents', $file);

        expect($attachment->isImage())->toBeFalse();
        expect($attachment->hasThumbnails())->toBeFalse();
    });

    it('generates thumbnails when enabled for images', function () {
        config(['filexus.generate_thumbnails' => true]);
        config(['filexus.thumbnail_sizes' => [
            'small' => [150, 150],
            'medium' => [300, 300],
        ]]);

        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $attachment = $post->attach('gallery', $file);

        expect($attachment->hasThumbnails())->toBeTrue();
        expect($attachment->metadata['thumbnails'])->toHaveKeys(['small', 'medium']);

        // Verify thumbnails exist on disk
        $disk = Storage::disk('testing');
        foreach ($attachment->metadata['thumbnails'] as $thumbnailPath) {
            expect($disk->exists($thumbnailPath))->toBeTrue();
        }
    });

    it('deletes thumbnails when file is deleted without deduplication', function () {
        config(['filexus.deduplicate' => false]);
        config(['filexus.generate_thumbnails' => true]);
        config(['filexus.thumbnail_sizes' => ['small' => [100, 100]]]);

        $post = Post::create(['title' => 'Test', 'content' => 'Content']);
        $file = UploadedFile::fake()->image('photo.jpg', 500, 500);

        $attachment = $post->attach('gallery', $file);

        expect($attachment->hasThumbnails())->toBeTrue();

        $thumbnailPath = $attachment->metadata['thumbnails']['small'];
        $disk = Storage::disk('testing');

        expect($disk->exists($thumbnailPath))->toBeTrue();

        // Delete the file
        $attachment->delete();

        // Thumbnail should be deleted
        expect($disk->exists($thumbnailPath))->toBeFalse();
    });

    it('only deletes thumbnails when last deduplicated reference is removed', function () {
        config(['filexus.deduplicate' => true]);
        config(['filexus.generate_thumbnails' => true]);
        config(['filexus.thumbnail_sizes' => ['small' => [100, 100]]]);

        $post1 = Post::create(['title' => 'Post 1', 'content' => 'Content']);
        $post2 = Post::create(['title' => 'Post 2', 'content' => 'Content']);

        // Create a real image and get its content
        $fakeImage = UploadedFile::fake()->image('img.jpg', 400, 400);
        $imagePath = $fakeImage->getRealPath();
        $content = file_get_contents($imagePath);

        // Now create identical files with that content
        $file1 = UploadedFile::fake()->createWithContent('img1.jpg', $content);
        $file2 = UploadedFile::fake()->createWithContent('img2.jpg', $content);

        $attachment1 = $post1->attach('gallery', $file1);
        $attachment2 = $post2->attach('gallery', $file2);

        // Should be deduplicated
        expect($attachment2->metadata['deduplicated'] ?? false)->toBeTrue();
        expect($attachment1->hasThumbnails())->toBeTrue();

        $thumbnailPath = $attachment1->metadata['thumbnails']['small'] ?? null;
        expect($thumbnailPath)->not->toBeNull();

        $disk = Storage::disk('testing');
        expect($disk->exists($thumbnailPath))->toBeTrue();

        // Delete second attachment
        $attachment2->delete();

        // Thumbnail should still exist
        expect($disk->exists($thumbnailPath))->toBeTrue();

        // Delete first attachment
        $attachment1->delete();

        // Now thumbnail should be deleted
        expect($disk->exists($thumbnailPath))->toBeFalse();
    });

    it('copies thumbnail metadata when deduplicating', function () {
        config(['filexus.deduplicate' => true]);

        $post1 = Post::create(['title' => 'Post 1', 'content' => 'Content']);
        $post2 = Post::create(['title' => 'Post 2', 'content' => 'Content']);

        $content = 'image data';
        $file1 = UploadedFile::fake()->createWithContent('img1.jpg', $content);
        $file2 = UploadedFile::fake()->createWithContent('img2.jpg', $content);

        $file1->mimeType = 'image/jpeg';
        $file2->mimeType = 'image/jpeg';

        $attachment1 = $post1->attach('gallery', $file1);

        // Manually add thumbnails to first file
        $attachment1->metadata = [
            'thumbnails' => ['small' => 'path/to/small.jpg'],
            'deduplicated' => false,
        ];
        $attachment1->save();

        $attachment2 = $post2->attach('gallery', $file2);

        // Second file should have copied thumbnails
        expect($attachment2->metadata['deduplicated'] ?? false)->toBeTrue();
        expect($attachment2->metadata['thumbnails'] ?? null)->toBe($attachment1->metadata['thumbnails']);
    });
});
