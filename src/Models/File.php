<?php

declare(strict_types=1);

namespace Filexus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * File Model
 *
 * Represents a file attachment in the system.
 *
 * @property int|string $id
 * @property string $disk
 * @property string $path
 * @property string $collection
 * @property string $fileable_type
 * @property int|string $fileable_id
 * @property string $original_name
 * @property string $mime
 * @property string $extension
 * @property int $size
 * @property string $hash
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int $reference_count
 * @property-read Model $fileable
 */
class File extends Model
{
    use HasUniqueStringIds;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'files';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'disk',
        'path',
        'collection',
        'fileable_type',
        'fileable_id',
        'original_name',
        'mime',
        'extension',
        'size',
        'hash',
        'metadata',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'size' => 'integer',
    ];

    /**
     * Get the parent fileable model.
     *
     * @return MorphTo<Model, $this>
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include files from a specific collection.
     *
     * @param Builder<File> $query
     * @param string $collection
     * @return Builder<File>
     */
    public function scopeWhereCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }

    /**
     * Scope a query to only include expired files.
     *
     * @param Builder<File> $query
     * @return Builder<File>
     */
    public function scopeWhereExpired(Builder $query): Builder
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        return $query;
    }

    /**
     * Scope a query to only include non-expired files.
     *
     * @param Builder<File> $query
     * @return Builder<File>
     */
    public function scopeWhereNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope a query to only include orphaned files (where parent model doesn't exist).
     *
     * @param Builder<File> $query
     * @return Builder<File>
     */
    public function scopeWhereOrphaned(Builder $query): Builder
    {
        return $query->whereDoesntHave('fileable');
    }

    /**
     * Get the full URL to the file.
     *
     * @return string
     */
    public function url(): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);
        return $disk->url($this->path);
    }

    /**
     * Get thumbnail URLs if thumbnails exist.
     *
     * @return array<string, string> Array of size => url
     */
    public function thumbnailUrls(): array
    {
        if (!isset($this->metadata['thumbnails'])) {
            return [];
        }

        $urls = [];
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);

        foreach ($this->metadata['thumbnails'] as $size => $path) {
            $urls[$size] = $disk->url($path);
        }

        return $urls;
    }

    /**
     * Get a specific thumbnail URL.
     *
     * @param string $size
     * @return string|null
     */
    public function thumbnailUrl(string $size): ?string
    {
        if (!isset($this->metadata['thumbnails'][$size])) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);
        return $disk->url($this->metadata['thumbnails'][$size]);
    }

    /**
     * Check if thumbnails exist for this file.
     *
     * @return bool
     */
    public function hasThumbnails(): bool
    {
        return isset($this->metadata['thumbnails']) && !empty($this->metadata['thumbnails']);
    }

    /**
     * Get a temporary URL for the file (if supported by the disk).
     *
     * @param Carbon $expiration
     * @return string
     */
    public function temporaryUrl(Carbon $expiration): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);
        return $disk->temporaryUrl($this->path, $expiration);
    }

    /**
     * Check if the file exists in storage.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Delete the file from storage.
     *
     * @return bool
     */
    public function deleteFromStorage(): bool
    {
        if ($this->exists()) {
            return Storage::disk($this->disk)->delete($this->path);
        }

        return false;
    }

    /**
     * Check if the file is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get human-readable file size.
     *
     * @return string
     */
    public function getHumanReadableSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the file is an image.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /**
     * Check if the file is a video.
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }

    /**
     * Check if the file is an audio file.
     *
     * @return bool
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime, 'audio/');
    }

    /**
     * Check if the file is a PDF.
     *
     * @return bool
     */
    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        $keyType = config('filexus.primary_key_type', 'id');

        if (in_array($keyType, ['uuid', 'ulid'])) {
            return ['id'];
        }

        return [];
    }

    /**
     * Generate a new unique key for the model.
     *
     * @return string
     */
    public function newUniqueId(): string
    {
        $keyType = config('filexus.primary_key_type', 'id');

        return match ($keyType) {
            'uuid' => strtolower((string) Str::uuid()),
            'ulid' => strtolower((string) Str::ulid()),
            default => '',
        };
    }

    /**
     * Determine if given key is valid.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function isValidUniqueId($value): bool
    {
        $keyType = config('filexus.primary_key_type', 'id');

        return match ($keyType) {
            'uuid' => Str::isUuid($value),
            'ulid' => Str::isUlid($value),
            default => false,
        };
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing(): bool
    {
        $keyType = config('filexus.primary_key_type', 'id');
        return $keyType === 'id';
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType(): string
    {
        $keyType = config('filexus.primary_key_type', 'id');
        return in_array($keyType, ['uuid', 'ulid']) ? 'string' : 'int';
    }

    /**
     * Get the number of references to this file (based on hash).
     * Useful when deduplication is enabled.
     *
     * @return Attribute
     */
    protected function referenceCount(): Attribute
    {
        return Attribute::make(
            get: fn() => static::where('hash', $this->hash)->count(),
        );
    }

    /**
     * Check if this is the last reference to the file.
     * Returns true if this is the only File record with this hash.
     *
     * @return bool
     */
    public function isLastReference(): bool
    {
        return $this->reference_count === 1;
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        // Automatically delete the file from storage when the model is deleted
        static::deleting(function (File $file) {
            // Check if deduplication is enabled
            $deduplicate = config('filexus.deduplicate', false);

            if ($deduplicate) {
                // Only delete the physical file if no other File records reference it
                $otherReferences = static::where('path', $file->path)
                    ->where('disk', $file->disk)
                    ->where('id', '!=', $file->id)
                    ->exists();

                if (!$otherReferences) {
                    $file->deleteFromStorage();

                    // Also delete thumbnails if present
                    if (isset($file->metadata['thumbnails'])) {
                        $thumbnailGenerator = app(\Filexus\Services\ThumbnailGenerator::class);
                        $thumbnailGenerator->deleteThumbnails($file->disk, $file->metadata['thumbnails']);
                    }
                }
            } else {
                // Deduplication not enabled, always delete the physical file
                $file->deleteFromStorage();

                // Also delete thumbnails if present
                if (isset($file->metadata['thumbnails'])) {
                    $thumbnailGenerator = app(\Filexus\Services\ThumbnailGenerator::class);
                    $thumbnailGenerator->deleteThumbnails($file->disk, $file->metadata['thumbnails']);
                }
            }
        });
    }
}
