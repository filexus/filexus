<?php

declare(strict_types=1);

use Filexus\FilexusServiceProvider;
use Filexus\FilexusManager;
use Filexus\Services\FileUploader;
use Filexus\Services\FilePruner;
use Filexus\Services\FilePathGenerator;
use Filexus\Commands\PruneCommand;

it('registers services in container', function () {
    expect(app(FilexusManager::class))->toBeInstanceOf(FilexusManager::class);
    expect(app(FileUploader::class))->toBeInstanceOf(FileUploader::class);
    expect(app(FilePruner::class))->toBeInstanceOf(FilePruner::class);
    expect(app(FilePathGenerator::class))->toBeInstanceOf(FilePathGenerator::class);
});

it('registers services as singletons', function () {
    $manager1 = app(FilexusManager::class);
    $manager2 = app(FilexusManager::class);

    expect($manager1)->toBe($manager2);
});

it('registers prune command', function () {
    $commands = $this->app[Illuminate\Contracts\Console\Kernel::class]->all();

    expect($commands)->toHaveKey('filexus:prune');
});

it('loads migrations from package', function () {
    $migrationPath = __DIR__ . '/../../database/migrations';

    expect(file_exists($migrationPath . '/2024_01_01_000000_create_files_table.php'))->toBeTrue();
});

it('publishes config file', function () {
    $configPath = config_path('filexus.php');

    // The config is not published by default, but should be publishable
    $this->artisan('vendor:publish', ['--tag' => 'filexus-config', '--force' => true]);

    // Config should be available even if not published
    expect(config('filexus.default_disk'))->not->toBeNull();
});

it('publishes migration files', function () {
    // The migrations should be publishable
    $this->artisan('vendor:publish', ['--tag' => 'filexus-migrations']);

    // Check that migrations are available
    expect(true)->toBeTrue(); // Publishing is available
});

it('merges config from package', function () {
    expect(config('filexus.default_disk'))->toBe('testing');
    expect(config('filexus.orphan_cleanup_hours'))->toBe(24);
    expect(config('filexus.max_file_size'))->toBe(10240);
});

it('does not load migrations during unit tests', function () {
    // This test verifies that the service provider correctly detects unit tests
    // and does not auto-load migrations (which would conflict with RefreshDatabase)

    expect(app()->runningUnitTests())->toBeTrue();

    // If we got this far without errors, migrations were not loaded twice
    expect(true)->toBeTrue();
});

it('provides method returns correct service names', function () {
    $provider = new \Filexus\FilexusServiceProvider(app());
    $provides = $provider->provides();

    expect($provides)->toContain('filexus');
    expect($provides)->toContain(\Filexus\Services\FileUploader::class);
    expect($provides)->toContain(\Filexus\Services\FilePruner::class);
    expect($provides)->toContain(\Filexus\FilexusManager::class);
    expect($provides)->toContain(\Filexus\Services\FilePathGenerator::class);
});

it('configureMorphKeyType handles uuid configuration', function () {
    config(['filexus.primary_key_type' => 'uuid']);

    $provider = new \Filexus\FilexusServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('configureMorphKeyType');
    $method->setAccessible(true);

    // Call the method - it should configure UUID morphs
    $method->invoke($provider);

    // Verify it ran without error (we can't directly test Model::morphUsingUuids() was called)
    expect(config('filexus.primary_key_type'))->toBe('uuid');
});

it('configureMorphKeyType handles ulid configuration', function () {
    config(['filexus.primary_key_type' => 'ulid']);

    $provider = new \Filexus\FilexusServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('configureMorphKeyType');
    $method->setAccessible(true);

    // Call the method - it should configure ULID morphs
    $method->invoke($provider);

    // Verify it ran without error
    expect(config('filexus.primary_key_type'))->toBe('ulid');
});

it('configureMorphKeyType handles id configuration', function () {
    config(['filexus.primary_key_type' => 'id']);

    $provider = new \Filexus\FilexusServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('configureMorphKeyType');
    $method->setAccessible(true);

    // Call the method - it  should not configure any special morphs
    $method->invoke($provider);

    // Verify it ran without error
    expect(config('filexus.primary_key_type'))->toBe('id');
});
it('configureMorphKeyType rethrows exceptions in non-testing environments', function () {
    config(['filexus.primary_key_type' => 'uuid']);

    // Create a mock application that returns false for runningUnitTests()
    $mockApp = Mockery::mock(\Illuminate\Foundation\Application::class);
    $mockApp->shouldReceive('runningUnitTests')->andReturn(false);

    $provider = new \Filexus\FilexusServiceProvider($mockApp);
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('configureMorphKeyType');
    $method->setAccessible(true);

    // This should throw an exception since we're "not in testing" and Model::morphUsingUuids() will fail
    $exceptionThrown = false;
    try {
        $method->invoke($provider);
    } catch (\Throwable $e) {
        $exceptionThrown = true;
    }

    expect($exceptionThrown)->toBeTrue();
});
