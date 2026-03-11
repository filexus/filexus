<?php

declare(strict_types=1);

namespace Filexus\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Database\Eloquent\Model;
use Filexus\Models\File;

/**
 * Event fired after a file has been uploaded.
 */
class FileUploaded
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model the file is attached to
     * @param File $file The uploaded file model
     */
    public function __construct(
        public readonly Model $model,
        public readonly File $file,
    ) {
    }
}
