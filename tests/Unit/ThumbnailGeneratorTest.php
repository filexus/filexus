<?php

declare(strict_types=1);

use Filexus\Services\ThumbnailGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('testing');
});

describe('ThumbnailGenerator', function () {
    it('checks if intervention image is available', function () {
        $generator = app(ThumbnailGenerator::class);

        $available = $generator->isAvailable();

        expect($available)->toBeTrue();
    });

    it('generates thumbnails for real images', function () {
        $generator = app(ThumbnailGenerator::class);

        // Create a real test image using UploadedFile::fake()
        $fakeImage = UploadedFile::fake()->image('test.jpg', 800, 600);

        // Store the image
        $imagePath = 'test/image.jpg';
        Storage::disk('testing')->putFileAs('test', $fakeImage, 'image.jpg');

        $sizes = [
            'small' => [150, 150],
            'medium' => [300, 300],
        ];

        $thumbnails = $generator->generate('testing', $imagePath, $sizes);

        expect($thumbnails)->toHaveCount(2);
        expect($thumbnails)->toHaveKeys(['small', 'medium']);

        // Verify thumbnails were created
        Storage::disk('testing')->assertExists($thumbnails['small']);
        Storage::disk('testing')->assertExists($thumbnails['medium']);
    });

    it('returns empty array when sizes are empty', function () {
        $generator = app(ThumbnailGenerator::class);

        $result = $generator->generate('testing', 'test/image.jpg', []);

        expect($result)->toBeEmpty();
    });

    it('returns empty array when file does not exist', function () {
        $generator = app(ThumbnailGenerator::class);

        $result = $generator->generate('testing', 'nonexistent/image.jpg', [
            'small' => [150, 150],
        ]);

        expect($result)->toBeEmpty();
    });

    it('handles invalid image content gracefully', function () {
        $generator = app(ThumbnailGenerator::class);

        Storage::disk('testing')->put('test/invalid.jpg', 'not a real image');

        $result = $generator->generate('testing', 'test/invalid.jpg', [
            'small' => [150, 150],
        ]);

        // Should return empty array instead of throwing
        expect($result)->toBeEmpty();
    });

    it('generates multiple thumbnail sizes', function () {
        $generator = app(ThumbnailGenerator::class);

        $fakeImage = UploadedFile::fake()->image('photo.jpg', 1000, 1000);

        $imagePath = 'test/photo.jpg';
        Storage::disk('testing')->putFileAs('test', $fakeImage, 'photo.jpg');

        $sizes = [
            'thumb' => [50, 50],
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [600, 600],
        ];

        $thumbnails = $generator->generate('testing', $imagePath, $sizes);

        expect($thumbnails)->toHaveCount(4);

        foreach (['thumb', 'small', 'medium', 'large'] as $size) {
            expect($thumbnails)->toHaveKey($size);
            Storage::disk('testing')->assertExists($thumbnails[$size]);
        }
    });

    it('deletes thumbnails from storage', function () {
        $generator = app(ThumbnailGenerator::class);

        Storage::disk('testing')->put('test/thumb1.jpg', 'content1');
        Storage::disk('testing')->put('test/thumb2.jpg', 'content2');

        Storage::disk('testing')->assertExists('test/thumb1.jpg');
        Storage::disk('testing')->assertExists('test/thumb2.jpg');

        $generator->deleteThumbnails('testing', ['test/thumb1.jpg', 'test/thumb2.jpg']);

        Storage::disk('testing')->assertMissing('test/thumb1.jpg');
        Storage::disk('testing')->assertMissing('test/thumb2.jpg');
    });

    it('continues deleting even if some thumbnails do not exist', function () {
        $generator = app(ThumbnailGenerator::class);

        Storage::disk('testing')->put('test/thumb1.jpg', 'content');
        Storage::disk('testing')->put('test/thumb3.jpg', 'content');

        $generator->deleteThumbnails('testing', [
            'test/thumb1.jpg',
            'test/nonexistent.jpg',
            'test/thumb3.jpg',
        ]);

        Storage::disk('testing')->assertMissing('test/thumb1.jpg');
        Storage::disk('testing')->assertMissing('test/thumb3.jpg');
    });

    it('handles different image formats', function () {
        $generator = app(ThumbnailGenerator::class);

        // Test with PNG
        $pngImage = UploadedFile::fake()->image('test.png', 500, 500);
        Storage::disk('testing')->putFileAs('test', $pngImage, 'test.png');

        $thumbnails = $generator->generate('testing', 'test/test.png', [
            'small' => [100, 100],
        ]);

        expect($thumbnails)->toHaveCount(1);
        Storage::disk('testing')->assertExists($thumbnails['small']);
    });

    it('works with files in root directory', function () {
        $generator = app(ThumbnailGenerator::class);

        $image = UploadedFile::fake()->image('root.jpg', 400, 400);
        Storage::disk('testing')->put('root.jpg', $image->get());

        $thumbnails = $generator->generate('testing', 'root.jpg', [
            'thumb' => [50, 50],
        ]);

        expect($thumbnails)->toHaveCount(1);
        Storage::disk('testing')->assertExists($thumbnails['thumb']);
    });
});
