<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Filexus\Traits\HasFiles;

class TestPostWithId extends Model
{
    use HasFiles;

    protected $table = 'posts';
    protected $guarded = [];
}
