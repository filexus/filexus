<?php

declare(strict_types=1);

namespace Filexus\Exceptions;

/**
 * Exception thrown when file not found.
 */
class FileNotFoundException extends FilexusException
{
    /**
     * Create a new exception for a file not found by ID.
     *
     * @param int|string $fileId
     * @return self
     */
    public static function withId(int|string $fileId): self
    {
        return new self("File with ID {$fileId} not found.");
    }

    /**
     * Create a new exception for a file not found in collection.
     *
     * @param string $collection
     * @return self
     */
    public static function inCollection(string $collection): self
    {
        return new self("No file found in collection '{$collection}'.");
    }
}
