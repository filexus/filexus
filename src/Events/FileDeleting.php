<?php

declare(strict_types=1);

namespace Filexus\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Filexus\Models\File;

/**
 * Event fired before a file is deleted.
 */
class FileDeleting
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param File $file The file being deleted
     */
    public function __construct(
        public readonly File $file,
    ) {
    }
}
