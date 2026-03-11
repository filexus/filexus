<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Filexus\FilexusServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Perform any work that should take place once the database has been refreshed.
     *
     * @return void
     */
    protected function afterRefreshingDatabase(): void
    {
        // Create posts table for testing
        Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // Create dummy_models table for testing orphaned files
        Schema::create('dummy_models', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilexusServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup filesystem
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing'),
        ]);

        $app['config']->set('filexus.default_disk', 'testing');
    }
}
