<?php

declare(strict_types=1);

use Filexus\Commands\PruneCommand;
use Filexus\FilexusManager;
use Filexus\Models\File;
use Tests\Fixtures\Post;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('testing');
});

it('can prune both expired and orphaned files by default', function () {
    $manager = \Mockery::mock(FilexusManager::class);
    $manager->shouldReceive('pruneExpired')->once()->andReturn(3);
    $manager->shouldReceive('pruneOrphaned')->once()->with(null)->andReturn(2);

    $this->app->instance(FilexusManager::class, $manager);

    $this->artisan(PruneCommand::class)
        ->expectsOutput('Starting file pruning...')
        ->expectsOutput('Pruning expired files...')
        ->expectsOutput('  • Deleted 3 expired file(s)')
        ->expectsOutput('Pruning orphaned files (older than 24 hours)...')
        ->expectsOutput('  • Deleted 2 orphaned file(s)')
        ->expectsOutput('✓ Successfully pruned 5 file(s)')
        ->assertExitCode(0);
});

it('can prune only expired files with --expired flag', function () {
    $manager = \Mockery::mock(FilexusManager::class);
    $manager->shouldReceive('pruneExpired')->once()->andReturn(2);
    $manager->shouldNotReceive('pruneOrphaned');

    $this->app->instance(FilexusManager::class, $manager);

    $this->artisan(PruneCommand::class, ['--expired' => true])
        ->expectsOutput('Pruning expired files...')
        ->expectsOutput('  • Deleted 2 expired file(s)')
        ->assertExitCode(0);
});

it('can prune only orphaned files with --orphaned flag', function () {
    $manager = \Mockery::mock(FilexusManager::class);
    $manager->shouldReceive('pruneOrphaned')->once()->with(null)->andReturn(4);
    $manager->shouldNotReceive('pruneExpired');

    $this->app->instance(FilexusManager::class, $manager);

    $this->artisan(PruneCommand::class, ['--orphaned' => true])
        ->expectsOutput('Pruning orphaned files (older than 24 hours)...')
        ->expectsOutput('  • Deleted 4 orphaned file(s)')
        ->assertExitCode(0);
});

it('can specify custom hours old for orphaned files', function () {
    $manager = \Mockery::mock(FilexusManager::class);
    $manager->shouldReceive('pruneOrphaned')->once()->with(48)->andReturn(1);
    $manager->shouldNotReceive('pruneExpired');

    $this->app->instance(FilexusManager::class, $manager);

    $this->artisan(PruneCommand::class, ['--orphaned' => true, '--hours-old' => 48])
        ->expectsOutput('Pruning orphaned files (older than 48 hours)...')
        ->expectsOutput('  • Deleted 1 orphaned file(s)')
        ->assertExitCode(0);
});

it('shows message when no files to prune', function () {
    $manager = \Mockery::mock(FilexusManager::class);
    $manager->shouldReceive('pruneExpired')->once()->andReturn(0);
    $manager->shouldReceive('pruneOrphaned')->once()->andReturn(0);

    $this->app->instance(FilexusManager::class, $manager);

    $this->artisan(PruneCommand::class)
        ->expectsOutput('No files to prune.')
        ->assertExitCode(0);
});

it('shows no expired files message when none found', function () {
    $manager = \Mockery::mock(FilexusManager::class);
    $manager->shouldReceive('pruneExpired')->once()->andReturn(0);
    $manager->shouldReceive('pruneOrphaned')->once()->andReturn(2);

    $this->app->instance(FilexusManager::class, $manager);

    $this->artisan(PruneCommand::class)
        ->expectsOutput('  • No expired files found')
        ->expectsOutput('  • Deleted 2 orphaned file(s)')
        ->assertExitCode(0);
});

it('shows no orphaned files message when none found', function () {
    $manager = \Mockery::mock(FilexusManager::class);
    $manager->shouldReceive('pruneExpired')->once()->andReturn(1);
    $manager->shouldReceive('pruneOrphaned')->once()->andReturn(0);

    $this->app->instance(FilexusManager::class, $manager);

    $this->artisan(PruneCommand::class)
        ->expectsOutput('  • Deleted 1 expired file(s)')
        ->expectsOutput('  • No orphaned files found')
        ->assertExitCode(0);
});
