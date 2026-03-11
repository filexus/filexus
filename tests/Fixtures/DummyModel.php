<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Dummy model for testing orphaned files.
 */
class DummyModel extends Model
{
    protected $table = 'dummy_models';
    protected $fillable = ['name'];
}
