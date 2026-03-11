<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Filexus\Traits\HasFiles;

/**
 * Test model with custom file collection configuration.
 */
class PostWithCustomCollections extends Model
{
    use HasFiles;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'posts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['title', 'content'];

    /**
     * File collection configuration.
     *
     * @var array<string, array<string, mixed>>
     */
    protected $fileCollections = [
        'avatar' => [
            'multiple' => false,
        ],
        'documents' => [
            'multiple' => true,
            'max_file_size' => 20480,
        ],
    ];
}
