<?php

declare(strict_types=1);

namespace Filexus\Traits;

use Filexus\Models\File;
use Filexus\Services\FileUploader;
use Filexus\Exceptions\InvalidCollectionException;
use Filexus\Exceptions\FileNotFoundException;
use Filexus\Events\FileDeleting;
use Filexus\Events\FileDeleted;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;

/**
 * Trait HasFiles
 *
 * Add file attachment capability to Eloquent models.
 *
 * @property-read EloquentCollection<int, File> $files
 */
trait HasFiles
{
    /**
     * Get all files attached to this model.
     *
     * @param string|null $collection
     * @return MorphMany
     */
    public function files(?string $collection = null): MorphMany
    {
        $builder = $this->morphMany(File::class, 'fileable');

        if($collection) {
            $builder->whereCollection($collection);
        }

        return $builder;
    }

    /**
     * Get files from a specific collection, or all files if no collection specified.
     *
     * @param string|null $collection
     * @return EloquentCollection<int, File>
     */
    public function getFiles(?string $collection = null): EloquentCollection
    {
        $query = $this->files();

        if ($collection !== null) {
            $query->whereCollection($collection);
        }

        return $query->get();
    }

    /**
     * Get files from a specific collection, using eager-loaded relationships if available.
     * This method prevents N+1 queries by checking if the 'files' relationship is already loaded.
     *
     * @param string|null $collection
     * @return EloquentCollection<int, File>
     */
    public function getFilesFromLoaded(?string $collection = null): EloquentCollection
    {
        // If files relationship is loaded, filter from the collection
        if ($this->relationLoaded('files')) {
            if ($collection === null) {
                return $this->files;
            }

            return $this->files->where('collection', $collection)->values();
        }

        // Otherwise, fall back to querying
        return $this->getFiles($collection);
    }

    /**
     * Get a single file from a collection.
     * Useful for single-file collections like 'avatar' or 'thumbnail'.
     *
     * @param string $collection
     * @return File|null
     */
    public function file(string $collection): ?File
    {
        return $this->files()->whereCollection($collection)->first();
    }

    /**
     * Get a single file from a collection, using eager-loaded relationships if available.
     * This method prevents N+1 queries by checking if the 'files' relationship is already loaded.
     *
     * @param string $collection
     * @return File|null
     */
    public function fileFromLoaded(string $collection): ?File
    {
        // If files relationship is loaded, filter from the collection
        if ($this->relationLoaded('files')) {
            return $this->files->where('collection', $collection)->first();
        }

        // Otherwise, fall back to querying
        return $this->file($collection);
    }

    /**
     * Attach a file to this model in a specific collection.
     *
     * @param string $collection
     * @param UploadedFile $file
     * @param array<string, mixed> $options Additional options (e.g., ['expires_at' => Carbon instance])
     * @return File
     * @throws InvalidCollectionException
     */
    public function attach(string $collection, UploadedFile $file, array $options = []): File
    {
        $config = $this->getCollectionConfig($collection);

        // Check if this is a single-file collection
        if (!($config['multiple'] ?? true)) {
            // If a file already exists, throw an exception
            $existingFile = $this->file($collection);
            if ($existingFile !== null) {
                throw InvalidCollectionException::isSingleFile($collection);
            }
        }

        /** @var FileUploader $uploader */
        $uploader = App::make(FileUploader::class);

        return $uploader->upload($this, $collection, $file, $config, $options);
    }

    /**
     * Attach multiple files to this model in a specific collection.
     *
     * @param string $collection
     * @param array<int, UploadedFile> $files
     * @param array<string, mixed> $options Additional options (e.g., ['expires_at' => Carbon instance])
     * @return EloquentCollection<int, File>
     * @throws InvalidCollectionException
     */
    public function attachMany(string $collection, array $files, array $options = []): EloquentCollection
    {
        $config = $this->getCollectionConfig($collection);

        // Check if this collection allows multiple files
        if (!($config['multiple'] ?? true)) {
            throw InvalidCollectionException::isSingleFile($collection);
        }

        $uploadedFiles = new EloquentCollection();

        /** @var FileUploader $uploader */
        $uploader = App::make(FileUploader::class);

        foreach ($files as $file) {
            $uploadedFiles->push(
                $uploader->upload($this, $collection, $file, $config, $options)
            );
        }

        return $uploadedFiles;
    }

    /**
     * Replace a file in a collection.
     * The old file is deleted and a new one is attached.
     *
     * @param string $collection
     * @param UploadedFile $file
     * @param array<string, mixed> $options Additional options (e.g., ['expires_at' => Carbon instance])
     * @return File
     */
    public function replace(string $collection, UploadedFile $file, array $options = []): File
    {
        // Get and delete existing file(s)
        $existingFiles = $this->getFiles($collection);

        foreach ($existingFiles as $existingFile) {
            $this->detachFile($existingFile);
        }

        $config = $this->getCollectionConfig($collection);

        /** @var FileUploader $uploader */
        $uploader = App::make(FileUploader::class);

        return $uploader->upload($this, $collection, $file, $config, $options);
    }

    /**
     * Detach (delete) a file from this model by file ID.
     *
     * @param string $collection
     * @param int|string $fileId
     * @return bool
     * @throws FileNotFoundException
     */
    public function detach(string $collection, int|string $fileId): bool
    {
        $file = $this->files()
            ->whereCollection($collection)
            ->find($fileId);

        if ($file === null) {
            throw FileNotFoundException::withId($fileId);
        }

        return $this->detachFile($file);
    }

    /**
     * Detach all files from a collection.
     *
     * @param string $collection
     * @return int Number of files deleted
     */
    public function detachAll(string $collection): int
    {
        $files = $this->getFiles($collection);
        $count = 0;

        foreach ($files as $file) {
            if ($this->detachFile($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Detach a File model instance.
     *
     * @param File $file
     * @return bool
     */
    protected function detachFile(File $file): bool
    {
        event(new FileDeleting($file));

        $deleted = $file->delete();

        if ($deleted) {
            event(new FileDeleted($file));
        }

        return $deleted;
    }

    /**
     * Check if the model has any files in a specific collection.
     *
     * @param string $collection
     * @return bool
     */
    public function hasFile(string $collection): bool
    {
        return $this->files()->whereCollection($collection)->exists();
    }

    /**
     * Check if the model has any files at all.
     *
     * @return bool
     */
    public function hasFiles(): bool
    {
        return $this->files()->exists();
    }

    /**
     * Get the configuration for a specific collection.
     *
     * @param string $collection
     * @return array<string, mixed>
     */
    protected function getCollectionConfig(string $collection): array
    {
        // Check if the model has its own collection configuration
        if (property_exists($this, 'fileCollections') && isset($this->fileCollections[$collection])) {
            return array_merge(
                config('filexus.collections.default', []),
                $this->fileCollections[$collection]
            );
        }

        // Check global configuration
        if (config("filexus.collections.{$collection}")) {
            return config("filexus.collections.{$collection}");
        }

        // Fall back to default configuration
        return config('filexus.collections.default', [
            'multiple' => true,
            'max_file_size' => config('filexus.max_file_size', 10240),
            'allowed_mimes' => config('filexus.allowed_mimes', []),
        ]);
    }

    /**
     * Boot the trait.
     *
     * @return void
     */
    protected static function bootHasFiles(): void
    {
        // When the model is deleted, also delete its files
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->files()->each(function (File $file) {
                $file->delete();
            });
        });
    }
}
