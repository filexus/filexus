<?php

declare(strict_types=1);

namespace Filexus;

use Filexus\Commands\PruneCommand;
use Filexus\Services\FilePathGenerator;
use Filexus\Services\FileUploader;
use Filexus\Services\FilePruner;
use Filexus\Services\ThumbnailGenerator;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\ServiceProvider;

/**
 * Filexus Service Provider
 *
 * Registers all package services, configurations, migrations, and commands.
 */
class FilexusServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filexus.php',
            'filexus'
        );

        // Register services
        $this->app->singleton(FilePathGenerator::class, function ($app) {
            $generatorClass = config('filexus.path_generator', FilePathGenerator::class);
            return new $generatorClass();
        });

        $this->app->singleton(ThumbnailGenerator::class, function ($app) {
            return new ThumbnailGenerator();
        });

        $this->app->singleton(FileUploader::class, function ($app) {
            return new FileUploader(
                $app->make(FilePathGenerator::class),
                $app->make(ThumbnailGenerator::class)
            );
        });

        $this->app->singleton(FilePruner::class, function ($app) {
            return new FilePruner();
        });

        $this->app->singleton(FilexusManager::class, function ($app) {
            return new FilexusManager(
                $app->make(FileUploader::class),
                $app->make(FilePruner::class)
            );
        });

        // Register alias for easier access
        $this->app->alias(FilexusManager::class, 'filexus');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Configure morph key type based on configuration
        $this->configureMorphKeyType();

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/filexus.php' => config_path('filexus.php'),
        ], 'filexus-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'filexus-migrations');

        // @codeCoverageIgnoreStart
        // Load migrations from package if not published (but not in testing)
        if (!$this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
        // @codeCoverageIgnoreEnd

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneCommand::class,
            ]);
        }
    }

    /**
     * Configure the morph key type based on configuration.
     *
     * @return void
     */
    protected function configureMorphKeyType(): void
    {
        $keyType = config('filexus.primary_key_type', 'id');

        if ($keyType === 'uuid') {
            Builder::morphUsingUuids();
        } elseif ($keyType === 'ulid') {
            Builder::morphUsingUlids();
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            FilePathGenerator::class,
            ThumbnailGenerator::class,
            FileUploader::class,
            FilePruner::class,
            FilexusManager::class,
            'filexus',
        ];
    }
}
