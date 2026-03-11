<?php

declare(strict_types=1);

use Filexus\Exceptions\FileNotFoundException;
use Filexus\Exceptions\FileUploadException;
use Filexus\Exceptions\InvalidCollectionException;

it('FileNotFoundException can be created with id', function () {
    $exception = FileNotFoundException::withId(123);

    expect($exception)->toBeInstanceOf(FileNotFoundException::class);
    expect($exception->getMessage())->toBe('File with ID 123 not found.');
});

it('FileNotFoundException can be created for collection', function () {
    $exception = FileNotFoundException::inCollection('avatar');

    expect($exception)->toBeInstanceOf(FileNotFoundException::class);
    expect($exception->getMessage())->toContain('avatar');
    expect($exception->getMessage())->toContain('No file found');
});

it('FileUploadException can be created for failed upload', function () {
    $exception = FileUploadException::failedToUpload('Disk error');

    expect($exception)->toBeInstanceOf(FileUploadException::class);
    expect($exception->getMessage())->toBe('File upload failed: Disk error');
});

it('FileUploadException can be created for invalid file', function () {
    $exception = FileUploadException::invalidFile('Corrupted upload');

    expect($exception)->toBeInstanceOf(FileUploadException::class);
    expect($exception->getMessage())->toContain('Invalid file');
    expect($exception->getMessage())->toContain('Corrupted upload');
});

it('FileUploadException can be created for file too large', function () {
    $exception = FileUploadException::fileTooLarge(1024, 2048);

    expect($exception)->toBeInstanceOf(FileUploadException::class);
    expect($exception->getMessage())->toBe('File size (2048 KB) exceeds maximum allowed size (1024 KB).');
});

it('FileUploadException can be created for invalid mime type', function () {
    $exception = FileUploadException::invalidMimeType('application/pdf', ['image/jpeg', 'image/png']);

    expect($exception)->toBeInstanceOf(FileUploadException::class);
    expect($exception->getMessage())->toContain('application/pdf');
    expect($exception->getMessage())->toContain('image/jpeg');
});

it('InvalidCollectionException can be created for not configured collection', function () {
    $exception = InvalidCollectionException::notConfigured('bad-collection');

    expect($exception)->toBeInstanceOf(InvalidCollectionException::class);
    expect($exception->getMessage())->toContain('not configured');
    expect($exception->getMessage())->toContain('bad-collection');
});

it('InvalidCollectionException can be created for single file collection', function () {
    $exception = InvalidCollectionException::isSingleFile('avatar');

    expect($exception)->toBeInstanceOf(InvalidCollectionException::class);
    expect($exception->getMessage())->toContain("Collection 'avatar' only allows a single file");
});
