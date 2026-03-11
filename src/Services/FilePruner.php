<?php

declare(strict_types=1);

namespace Filexus\Services;

use Filexus\Models\File;
use Filexus\Events\FileDeleting;
use Filexus\Events\FileDeleted;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Service class for pruning expired and orphaned files.
 *
 * Responsible for:
 * - Finding expired files
 * - Finding orphaned files
 * - Deleting files and their storage
 */
class FilePruner
{
    /**
     * Prune expired files.
     *
     * @return int Number of files deleted
     */
    public function pruneExpired(): int
    {
        $expiredFiles = File::whereExpired()->get();

        return $this->deleteFiles($expiredFiles);
    }

    /**
     * Prune orphaned files (files whose parent model no longer exists).
     *
     * @param int|null $hoursOld Minimum age in hours before considering for deletion
     * @return int Number of files deleted
     */
    public function pruneOrphaned(?int $hoursOld = null): int
    {
        $hoursOld = $hoursOld ?? config('filexus.orphan_cleanup_hours', 24);
        $cutoffDate = Carbon::now()->subHours($hoursOld);

        // Get all files that are older than the cutoff
        $potentialOrphans = File::where('created_at', '<=', $cutoffDate)->get();

        $orphanedFiles = $potentialOrphans->filter(function (File $file) {
            // Check if the parent model exists
            return $file->fileable === null;
        });

        return $this->deleteFiles($orphanedFiles);
    }

    /**
     * Delete a collection of files.
     *
     * @param Collection<int, File> $files
     * @return int Number of files deleted
     */
    protected function deleteFiles(Collection $files): int
    {
        $deletedCount = 0;

        foreach ($files as $file) {
            try {
                // Dispatch the FileDeleting event
                event(new FileDeleting($file));

                // Delete the file record (this will also delete from storage via model event)
                $file->delete();

                // Dispatch the FileDeleted event
                event(new FileDeleted($file));

                $deletedCount++;
                // @codeCoverageIgnoreStart
            } catch (\Exception $e) {
                // Log the error but continue with other files
                report($e);
            }
            // @codeCoverageIgnoreEnd
        }

        return $deletedCount;
    }

    /**
     * Get statistics about files that can be pruned.
     *
     * @return array{expired: int, potentially_orphaned: int, total: int}
     */
    public function getStatistics(): array
    {
        $expiredCount = File::whereExpired()->count();

        $hoursOld = (int) config('filexus.orphan_cleanup_hours', 24);
        $cutoffDate = Carbon::now()->subHours($hoursOld);

        // This is a simplified count - actual orphan detection requires checking each file
        $oldFilesCount = File::where('created_at', '<=', $cutoffDate)->count();

        return [
            'expired' => (int) $expiredCount,
            'potentially_orphaned' => (int) $oldFilesCount,
            'total' => (int) ($expiredCount + $oldFilesCount),
        ];
    }
}
