<?php

declare(strict_types=1);

use Filexus\Services\FilePathGenerator;
use Tests\Fixtures\Post;
use Illuminate\Http\UploadedFile;

it('generates correct file paths', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');
    $generator = new FilePathGenerator();

    $path = $generator->generate($post, 'gallery', $file);

    expect($path)->toContain('posts')
        ->and($path)->toContain((string) $post->id)
        ->and($path)->toContain('gallery')
        ->and($path)->toEndWith('.jpg');
});

it('generates unique paths for different files', function () {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');
    $generator = new FilePathGenerator();

    $path1 = $generator->generate($post, 'gallery', $file);
    $path2 = $generator->generate($post, 'gallery', $file);

    expect($path1)->not->toBe($path2);
});
