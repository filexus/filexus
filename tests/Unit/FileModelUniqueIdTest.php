<?php

declare(strict_types=1);

use Filexus\Models\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

it('validates UUID format correctly using isValidUniqueId', function () {
    Config::set('filexus.primary_key_type', 'uuid');

    $file = new File();
    $reflection = new ReflectionClass($file);
    $method = $reflection->getMethod('isValidUniqueId');
    $method->setAccessible(true);

    // Test valid UUID
    $validUuid = Str::uuid()->toString();
    expect($method->invoke($file, $validUuid))->toBeTrue();

    // Test invalid UUID
    expect($method->invoke($file, 'not-a-uuid'))->toBeFalse();
    expect($method->invoke($file, '12345'))->toBeFalse();
});

it('validates ULID format correctly using isValidUniqueId', function () {
    Config::set('filexus.primary_key_type', 'ulid');

    $file = new File();
    $reflection = new ReflectionClass($file);
    $method = $reflection->getMethod('isValidUniqueId');
    $method->setAccessible(true);

    // Test valid ULID
    $validUlid = (string) Str::ulid();
    expect($method->invoke($file, $validUlid))->toBeTrue();

    // Test invalid ULID
    expect($method->invoke($file, 'not-a-ulid'))->toBeFalse();
    expect($method->invoke($file, '12345'))->toBeFalse();
});

it('returns false for non-uuid/ulid types in isValidUniqueId', function () {
    Config::set('filexus.primary_key_type', 'id');

    $file = new File();
    $reflection = new ReflectionClass($file);
    $method = $reflection->getMethod('isValidUniqueId');
    $method->setAccessible(true);

    // Should return false for regular ID type
    expect($method->invoke($file, '12345'))->toBeFalse();
    expect($method->invoke($file, 'anything'))->toBeFalse();
});
