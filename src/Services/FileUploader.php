<?php

declare(strict_types=1);

namespace Filexus\Services;

use Filexus\Models\File;
use Filexus\Events\FileUploading;
use Filexus\Events\FileUploaded;
use Filexus\Exceptions\FileUploadException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * Service class for handling file uploads.
 *
 * Responsible for:
 * - Validating files
 * - Storing files to disk
 * - Creating File model records
 * - Dispatching events
 * - Generating thumbnails for images
 */
class FileUploader
{
    /**
     * Create a new FileUploader instance.
     *
     * @param FilePathGenerator $pathGenerator
     * @param ThumbnailGenerator $thumbnailGenerator
     */
    public function __construct(
        protected FilePathGenerator $pathGenerator,
        protected ThumbnailGenerator $thumbnailGenerator,
    ) {
    }

    /**
     * Upload a file and create a File model record.
     *
     * @param Model $model The model to attach the file to
     * @param string $collection The collection name
     * @param UploadedFile $file The uploaded file
     * @param array<string, mixed> $config Collection configuration
     * @param array<string, mixed> $options Additional options (e.g., ['expires_at' => Carbon instance])
     * @return File The created File model
     * @throws FileUploadException
     */
    public function upload(Model $model, string $collection, UploadedFile $file, array $config = [], array $options = []): File
    {
        // Validate the file
        $this->validateFile($file, $config);

        // Dispatch the FileUploading event
        event(new FileUploading($model, $collection, $file));

        $disk = config('filexus.default_disk', 'public');

        // Calculate hash early for deduplication check
        $hash = hash_file('sha256', $file->getRealPath());

        // Check for deduplication
        $deduplicate = config('filexus.deduplicate', false);
        $existingFile = null;

        if ($deduplicate) {
            /** @var File|null $existingFile */
            $existingFile = File::where('hash', $hash)
                ->where('disk', $disk)
                ->first();
        }

        // Use a transaction to ensure atomicity
        return DB::transaction(function () use ($model, $collection, $file, $disk, $hash, $existingFile, $deduplicate, $options) {
            $metadata = [];
            $shouldGenerateThumbnails = false;

            // If deduplication is enabled and file exists, reuse the path
            if ($deduplicate && $existingFile) {
                $path = $existingFile->path;
                $metadata['deduplicated'] = true;
                $metadata['original_file_id'] = $existingFile->id;

                // Copy thumbnails from existing file if available
                if (isset($existingFile->metadata['thumbnails'])) {
                    $metadata['thumbnails'] = $existingFile->metadata['thumbnails'];
                }
            } else {
                // Generate new path and store the file
                $path = $this->pathGenerator->generate($model, $collection, $file);

                $stored = Storage::disk($disk)->putFileAs(
                    dirname($path),
                    $file,
                    basename($path)
                );

                if (!$stored) {
                    throw FileUploadException::failedToUpload('Could not store file to disk.');
                }

                $metadata['deduplicated'] = false;
                $shouldGenerateThumbnails = true;
            }

            // Create the File model
            $fileModel = new File([
                'disk' => $disk,
                'path' => $path,
                'collection' => $collection,
                'fileable_type' => get_class($model),
                'fileable_id' => $model->getKey(),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'extension' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
                'hash' => $hash,
                'metadata' => $metadata,
                'expires_at' => $options['expires_at'] ?? null,
            ]);

            $fileModel->save();

            // Generate thumbnails if enabled and this is a new file
            if ($shouldGenerateThumbnails && $this->shouldGenerateThumbnails($fileModel)) {
                $thumbnails = $this->thumbnailGenerator->generate(
                    $disk,
                    $path,
                    config('filexus.thumbnail_sizes', [])
                );

                if (!empty($thumbnails)) {
                    $metadata['thumbnails'] = $thumbnails;
                    $fileModel->metadata = $metadata;
                    $fileModel->save();
                }
            }

            // Dispatch the FileUploaded event
            event(new FileUploaded($model, $fileModel));

            return $fileModel;
        });
    }

    /**
     * Determine if thumbnails should be generated for this file.
     *
     * @param File $file
     * @return bool
     */
    protected function shouldGenerateThumbnails(File $file): bool
    {
        // Check if thumbnail generation is enabled
        if (!config('filexus.generate_thumbnails', false)) {
            return false;
        }

        // Check if the thumbnail generator is available
        // @codeCoverageIgnoreStart
        if (!$this->thumbnailGenerator->isAvailable()) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        // Check if the file is an image
        return $file->isImage();
    }

    /**
     * Validate the uploaded file against configuration constraints.
     *
     * @param UploadedFile $file
     * @param array<string, mixed> $config
     * @return void
     * @throws FileUploadException
     */
    protected function validateFile(UploadedFile $file, array $config): void
    {
        if (!$file->isValid()) {
            throw FileUploadException::invalidFile('File upload was not successful.');
        }

        // Check file size
        $maxSize = $config['max_file_size'] ?? config('filexus.max_file_size', 10240);
        $fileSizeKb = $file->getSize() / 1024;

        if ($fileSizeKb > $maxSize) {
            throw FileUploadException::fileTooLarge($maxSize, (int) $fileSizeKb);
        }

        // Check mime type if restrictions exist
        $allowedMimes = $config['allowed_mimes'] ?? config('filexus.allowed_mimes', []);

        if (!empty($allowedMimes)) {
            $fileMime = $file->getMimeType();

            if ($fileMime && !in_array($fileMime, $allowedMimes, true)) {
                throw FileUploadException::invalidMimeType($fileMime, $allowedMimes);
            }
        }
    }
}
