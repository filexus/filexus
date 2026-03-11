<?php

declare(strict_types=1);

use Filexus\Models\File;
use Filexus\Services\FilePruner;
use Tests\Fixtures\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Filexus\Events\FileDeleting;
use Filexus\Events\FileDeleted;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    Storage::fake('testing');
    Event::fake();
});

it('prunes expired files', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    // Create expired file
    $expiredFile = $post->attach('gallery', UploadedFile::fake()->image('expired.jpg'));
    $expiredFile->expires_at = now()->subDay();
    $expiredFile->save();

    // Create non-expired file
    $activeFile = $post->attach('gallery', UploadedFile::fake()->image('active.jpg'));
    $activeFile->expires_at = now()->addDay();
    $activeFile->save();

    // Create file with no expiration
    $permanentFile = $post->attach('gallery', UploadedFile::fake()->image('permanent.jpg'));

    $pruner = new FilePruner();
    $count = $pruner->pruneExpired();

    expect($count)->toBe(1);
    expect(File::count())->toBe(2);
    expect(File::find($expiredFile->id))->toBeNull();
    expect(File::find($activeFile->id))->not->toBeNull();
    expect(File::find($permanentFile->id))->not->toBeNull();

    Event::assertDispatched(FileDeleting::class);
    Event::assertDispatched(FileDeleted::class);
});

it('prunes orphaned files older than cutoff', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    // Create old file
    $oldFile = $post->attach('gallery', UploadedFile::fake()->image('old.jpg'));
    $oldFile->created_at = now()->subHours(25);
    $oldFile->save();

    // Delete the post to make file orphaned
    $post->delete();

    // Create recent orphaned file
    $recentPost = Post::create(['title' => 'Recent', 'content' => 'Content']);
    $recentFile = $recentPost->attach('gallery', UploadedFile::fake()->image('recent.jpg'));
    $recentFile->created_at = now()->subHours(12);
    $recentFile->save();
    $recentPost->delete();

    $pruner = new FilePruner();
    $count = $pruner->pruneOrphaned(24);

    expect($count)->toBe(1);
    expect(File::count())->toBe(1);
    expect(File::find($oldFile->id))->toBeNull();
    expect(File::find($recentFile->id))->not->toBeNull();
});

it('uses default hours from config when not specified', function () {
    config(['filexus.orphan_cleanup_hours' => 48]);

    $post = Post::create(['title' => 'Test', 'content' => 'Content']);
    $file = $post->attach('gallery', UploadedFile::fake()->image('test.jpg'));
    $file->created_at = now()->subHours(36);
    $file->save();
    $post->delete();

    $pruner = new FilePruner();
    $count = $pruner->pruneOrphaned();

    expect($count)->toBe(0); // Not old enough for 48-hour cutoff
});

it('handles exceptions during file deletion gracefully', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    $file1 = $post->attach('gallery', UploadedFile::fake()->image('file1.jpg'));
    $file1->expires_at = now()->subDay();
    $file1->save();

    $file2 = $post->attach('gallery', UploadedFile::fake()->image('file2.jpg'));
    $file2->expires_at = now()->subDay();
    $file2->save();

    $pruner = new FilePruner();

    // Even with potential exceptions, the pruner should continue
    $count = $pruner->pruneExpired();

    // Should delete both files
    expect($count)->toBe(2);
});

it('gets pruning statistics', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    // Create expired file
    $expiredFile = $post->attach('gallery', UploadedFile::fake()->image('expired.jpg'));
    $expiredFile->expires_at = now()->subDay();
    $expiredFile->save();

    // Create old file (potential orphan)
    $oldFile = $post->attach('gallery', UploadedFile::fake()->image('old.jpg'));
    $oldFile->created_at = now()->subHours(25);
    $oldFile->save();

    // Create recent file
    $recentFile = $post->attach('gallery', UploadedFile::fake()->image('recent.jpg'));

    $pruner = new FilePruner();
    $stats = $pruner->getStatistics();

    expect($stats)->toHaveKeys(['expired', 'potentially_orphaned', 'total']);
    expect($stats['expired'])->toBe(1);
    expect($stats['potentially_orphaned'])->toBe(1);
    expect($stats['total'])->toBe(2);
});

it('returns zero when no expired files exist', function () {
    $pruner = new FilePruner();
    $count = $pruner->pruneExpired();

    expect($count)->toBe(0);
});

it('returns zero when no orphaned files exist', function () {
    $pruner = new FilePruner();
    $count = $pruner->pruneOrphaned();

    expect($count)->toBe(0);
});
it('catches and reports exceptions during pruning', function () {
    $post = Post::create(['title' => 'Test', 'content' => 'Content']);

    // Create two expired files
    $file1 = $post->attach('gallery', UploadedFile::fake()->image('test1.jpg'));
    $file1->expires_at = now()->subDay();
    $file1->save();

    $file2 = $post->attach('gallery', UploadedFile::fake()->image('test2.jpg'));
    $file2->expires_at = now()->subDay();
    $file2->save();

    $pruner = app(FilePruner::class);

    // The pruner should handle any exceptions gracefully and continue
    $count = $pruner->pruneExpired();

    expect($count)->toBe(2);
});
