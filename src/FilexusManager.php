<?php

declare(strict_types=1);

namespace Filexus;

use Filexus\Models\File;
use Filexus\Services\FileUploader;
use Filexus\Services\FilePruner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Main manager class for Filexus operations.
 *
 * Provides a central point for coordinating file operations
 * and accessing services.
 */
class FilexusManager
{
    /**
     * Create a new FilexusManager instance.
     *
     * @param FileUploader $uploader
     * @param FilePruner $pruner
     */
    public function __construct(
        protected FileUploader $uploader,
        protected FilePruner $pruner,
    ) {
    }

    /**
     * Upload a file and attach it to a model.
     *
     * @param Model $model
     * @param string $collection
     * @param UploadedFile $file
     * @param array<string, mixed> $config
     * @return File
     */
    public function upload(Model $model, string $collection, UploadedFile $file, array $config = []): File
    {
        return $this->uploader->upload($model, $collection, $file, $config);
    }

    /**
     * Prune expired files.
     *
     * @return int Number of files deleted
     */
    public function pruneExpired(): int
    {
        return $this->pruner->pruneExpired();
    }

    /**
     * Prune orphaned files.
     *
     * @param int|null $hoursOld
     * @return int Number of files deleted
     */
    public function pruneOrphaned(?int $hoursOld = null): int
    {
        return $this->pruner->pruneOrphaned($hoursOld);
    }

    /**
     * Prune both expired and orphaned files.
     *
     * @return array{expired: int, orphaned: int, total: int}
     */
    public function pruneAll(): array
    {
        $expired = $this->pruneExpired();
        $orphaned = $this->pruneOrphaned();

        return [
            'expired' => $expired,
            'orphaned' => $orphaned,
            'total' => $expired + $orphaned,
        ];
    }

    /**
     * Get pruning statistics.
     *
     * @return array{expired: int, potentially_orphaned: int, total: int}
     */
    public function getPruneStatistics(): array
    {
        return $this->pruner->getStatistics();
    }

    /**
     * Find files by hash (for deduplication).
     *
     * @param string $hash
     * @return File|null
     */
    public function findByHash(string $hash): ?File
    {
        /** @var File|null */
        return File::where('hash', $hash)->first();
    }

    /**
     * Check if a file with the given hash already exists.
     *
     * @param string $hash
     * @return bool
     */
    public function hashExists(string $hash): bool
    {
        return File::where('hash', $hash)->exists();
    }
}
