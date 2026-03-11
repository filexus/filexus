<?php

declare(strict_types=1);

namespace Filexus\Exceptions;

/**
 * Exception thrown when file upload fails.
 */
class FileUploadException extends FilexusException
{
    /**
     * Create a new exception for a failed upload.
     *
     * @param string $reason
     * @return self
     */
    public static function failedToUpload(string $reason = 'Unknown error'): self
    {
        return new self("File upload failed: {$reason}");
    }

    /**
     * Create a new exception for an invalid file.
     *
     * @param string $reason
     * @return self
     */
    public static function invalidFile(string $reason): self
    {
        return new self("Invalid file: {$reason}");
    }

    /**
     * Create a new exception for file size violation.
     *
     * @param int $maxSize
     * @param int $actualSize
     * @return self
     */
    public static function fileTooLarge(int $maxSize, int $actualSize): self
    {
        return new self(
            "File size ({$actualSize} KB) exceeds maximum allowed size ({$maxSize} KB)."
        );
    }

    /**
     * Create a new exception for mime type violation.
     *
     * @param string $mime
     * @param array<int, string> $allowedMimes
     * @return self
     */
    public static function invalidMimeType(string $mime, array $allowedMimes): self
    {
        $allowed = implode(', ', $allowedMimes);
        return new self(
            "File mime type '{$mime}' is not allowed. Allowed types: {$allowed}"
        );
    }
}
