<?php

declare(strict_types=1);

namespace Filexus\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;

/**
 * Event fired before a file is uploaded.
 */
class FileUploading
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model the file will be attached to
     * @param string $collection The collection name
     * @param UploadedFile $file The file being uploaded
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $collection,
        public readonly UploadedFile $file,
    ) {
    }
}
