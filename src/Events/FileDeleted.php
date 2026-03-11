<?php

declare(strict_types=1);

namespace Filexus\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Filexus\Models\File;

/**
 * Event fired after a file has been deleted.
 */
class FileDeleted
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param File $file The deleted file model (still has data but no longer in DB)
     */
    public function __construct(
        public readonly File $file,
    ) {
    }
}
