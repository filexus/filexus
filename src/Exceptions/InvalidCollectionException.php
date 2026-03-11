<?php

declare(strict_types=1);

namespace Filexus\Exceptions;

/**
 * Exception thrown when a file collection is invalid or doesn't exist.
 */
class InvalidCollectionException extends FilexusException
{
    /**
     * Create a new exception for an invalid collection.
     *
     * @param string $collection
     * @return self
     */
    public static function notConfigured(string $collection): self
    {
        return new self("Collection '{$collection}' is not configured.");
    }

    /**
     * Create a new exception for a single-file collection violation.
     *
     * @param string $collection
     * @return self
     */
    public static function isSingleFile(string $collection): self
    {
        return new self("Collection '{$collection}' only allows a single file. Use replace() instead.");
    }
}
