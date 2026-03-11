<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Filexus\Traits\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Post model with soft deletes for testing.
 */
class SoftDeletablePost extends Model
{
    use HasFiles, SoftDeletes;

    protected $table = 'posts';
    protected $fillable = ['title', 'content'];
}
