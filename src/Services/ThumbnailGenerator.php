<?php

declare(strict_types=1);

namespace Filexus\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service class for generating image thumbnails.
 *
 * Requires intervention/image package to be installed.
 * If the package is not available, methods will gracefully return without errors.
 */
class ThumbnailGenerator
{
    /**
     * Check if the Image library is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return class_exists(\Intervention\Image\ImageManager::class);
    }

    /**
     * Generate thumbnails for an image file.
     *
     * @param string $disk The storage disk
     * @param string $path The path to the original image
     * @param array<string, array{0: int, 1: int}> $sizes Thumbnail sizes from config
     * @return array<string, string> Array of size => thumbnail_path
     */
    public function generate(string $disk, string $path, array $sizes): array
    {
        // @codeCoverageIgnoreStart
        if (!$this->isAvailable()) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        if (empty($sizes)) {
            return [];
        }

        $thumbnails = [];
        $diskInstance = Storage::disk($disk);

        // Get the image contents
        $imageContents = $diskInstance->get($path);
        if (!$imageContents) {
            return [];
        }

        try {
            // Create the image manager (supports GD and Imagick)
            // Intervention Image v3 uses static factory methods
            // @codeCoverageIgnoreStart
            $manager = $this->getDriver() === 'imagick'
                ? \Intervention\Image\ImageManager::imagick()
                : \Intervention\Image\ImageManager::gd();
            // @codeCoverageIgnoreEnd

            $image = $manager->read($imageContents);

            $pathInfo = pathinfo($path);
            $directory = $pathInfo['dirname'] ?? '';
            $filename = $pathInfo['filename'];
            $extension = $pathInfo['extension'] ?? 'jpg';

            foreach ($sizes as $sizeName => $dimensions) {
                [$width, $height] = $dimensions;

                // Create thumbnail
                $thumbnail = clone $image;
                $thumbnail->cover($width, $height);

                // Generate thumbnail path
                $thumbnailFilename = "{$filename}_{$sizeName}.{$extension}";
                $thumbnailPath = "{$directory}/thumbnails/{$thumbnailFilename}";

                // Save thumbnail - v3 uses encode() with format
                $encoded = $thumbnail->encode();
                $diskInstance->put($thumbnailPath, (string) $encoded);

                $thumbnails[$sizeName] = $thumbnailPath;
            }

            return $thumbnails;
        } catch (\Throwable $e) {
            // @codeCoverageIgnoreStart
            // Silently fail and return empty array
            // Could log this if needed
            report($e);
            return [];
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Delete thumbnails associated with a file.
     *
     * @param string $disk
     * @param array<string, string> $thumbnails
     * @return void
     */
    public function deleteThumbnails(string $disk, array $thumbnails): void
    {
        $diskInstance = Storage::disk($disk);

        foreach ($thumbnails as $thumbnailPath) {
            try {
                if ($diskInstance->exists($thumbnailPath)) {
                    $diskInstance->delete($thumbnailPath);
                }
                // @codeCoverageIgnoreStart
            } catch (\Throwable $e) {
                // Continue deleting other thumbnails even if one fails
                report($e);
            }
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Get the available image driver.
     *
     * @return string
     */
    protected function getDriver(): string
    {
        // Prefer GD as it's more commonly available
        if (extension_loaded('gd')) {
            return 'gd';
        }

        // @codeCoverageIgnoreStart
        if (extension_loaded('imagick')) {
            return 'imagick';
        }

        // Default to gd, will fail gracefully if not available
        return 'gd';
        // @codeCoverageIgnoreEnd
    }
}
