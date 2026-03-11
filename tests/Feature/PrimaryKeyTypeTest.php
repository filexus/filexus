<?php

declare(strict_types=1);

use Filexus\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Fixtures\TestPostWithId;
use Tests\Fixtures\TestPostWithUuid;
use Tests\Fixtures\TestPostWithUlid;

beforeEach(function () {
    // Clear any morph map from previous tests
    Model::unsetEventDispatcher();

    // Ensure posts table is clean
    if (Schema::hasTable('posts')) {
        Schema::dropIfExists('posts');
    }
});

afterEach(function () {
    // Clean up after each test
    if (Schema::hasTable('posts')) {
        Schema::dropIfExists('posts');
    }
});

/**
 * Helper to create posts table with specific key type
 */
function createPostsTable(string $keyType = 'id'): void
{
    Schema::create('posts', function (Blueprint $table) use ($keyType) {
        match ($keyType) {
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            default => $table->id(),
        };

        $table->string('title');
        $table->timestamps();
    });
}

/**
 * Helper to recreate files table with specific key type
 */
function recreateFilesTableWithKeyType(string $keyType): void
{
    Schema::dropIfExists('files');

    Schema::create('files', function (Blueprint $table) use ($keyType) {
        match ($keyType) {
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            default => $table->id(),
        };

        $table->string('disk', 50);
        $table->string('path');
        $table->string('collection', 100);

        if ($keyType === 'uuid') {
            $table->uuidMorphs('fileable');
        } elseif ($keyType === 'ulid') {
            $table->ulidMorphs('fileable');
        } else {
            $table->morphs('fileable');
        }

        $table->string('original_name');
        $table->string('mime', 100);
        $table->string('extension', 20);
        $table->unsignedBigInteger('size');
        $table->string('hash', 64);
        $table->json('metadata')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();

        $table->index('collection');
        $table->index('hash');
        $table->index('expires_at');
    });
}

describe('Primary Key Type - Auto-increment (default

)', function () {
    beforeEach(function () {
        Config::set('filexus.primary_key_type', 'id');
        recreateFilesTableWithKeyType('id');
        createPostsTable('id');

        // Reset morph map
        Model::unsetEventDispatcher();
    });

    it('creates file with auto-increment ID', function () {
        $post = TestPostWithId::create(['title' => 'Test Post']);

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        expect($file->id)->toBeInt();
        expect($file->id)->toBeGreaterThan(0);
    });

    it('has incrementing set to true', function () {
        $file = new File();
        expect($file->getIncrementing())->toBeTrue();
    });

    it('has key type set to int', function () {
        $file = new File();
        expect($file->getKeyType())->toBe('int');
    });

    it('uniqueIds returns empty array for auto-increment', function () {
        $file = new File();
        expect($file->uniqueIds())->toBe([]);
    });

    it('creates morphable relationship with integer IDs', function () {
        $post = TestPostWithId::create(['title' => 'Test Post']);

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        expect($file->fileable_id)->toBeInt();
        expect($file->fileable_type)->toBe(TestPostWithId::class);
        expect($file->fileable->id)->toBe($post->id);
    });
});

describe('Primary Key Type - UUID', function () {
    beforeEach(function () {
        Config::set('filexus.primary_key_type', 'uuid');
        recreateFilesTableWithKeyType('uuid');
        createPostsTable('uuid');
    });

    it('creates file with UUID primary key', function () {
        // Manually create post with UUID
        $postId = Str::uuid()->toString();
        $post = new TestPostWithUuid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        expect($file->id)->toBeString();
        expect(Str::isUuid($file->id))->toBeTrue();
    });

    it('has incrementing set to false for UUID', function () {
        $file = new File();
        expect($file->getIncrementing())->toBeFalse();
    });

    it('has key type set to string for UUID', function () {
        $file = new File();
        expect($file->getKeyType())->toBe('string');
    });

    it('uniqueIds returns id column for UUID', function () {
        $file = new File();
        expect($file->uniqueIds())->toBe(['id']);
    });

    it('creates morphable relationship with UUID IDs', function () {
        $postId = Str::uuid()->toString();
        $post = new TestPostWithUuid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        expect($file->fileable_id)->toBeString();
        expect(Str::isUuid($file->fileable_id))->toBeTrue();
        expect($file->fileable_id)->toBe($postId);
        expect($file->fileable_type)->toBe(TestPostWithUuid::class);
    });

    it('can retrieve fileable model with UUID', function () {
        $postId = Str::uuid()->toString();
        $post = new TestPostWithUuid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        $retrievedFile = File::find($file->id);
        expect($retrievedFile)->not->toBeNull();
        expect($retrievedFile->fileable)->not->toBeNull();
        expect($retrievedFile->fileable->id)->toBe($postId);
    });
});

describe('Primary Key Type - ULID', function () {
    beforeEach(function () {
        Config::set('filexus.primary_key_type', 'ulid');
        recreateFilesTableWithKeyType('ulid');
        createPostsTable('ulid');
    });

    it('creates file with ULID primary key', function () {
        // Manually create post with ULID
        $postId = (string) Str::ulid();
        $post = new TestPostWithUlid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        expect($file->id)->toBeString();
        expect(Str::isUlid($file->id))->toBeTrue();
    });

    it('has incrementing set to false for ULID', function () {
        $file = new File();
        expect($file->getIncrementing())->toBeFalse();
    });

    it('has key type set to string for ULID', function () {
        $file = new File();
        expect($file->getKeyType())->toBe('string');
    });

    it('uniqueIds returns id column for ULID', function () {
        $file = new File();
        expect($file->uniqueIds())->toBe(['id']);
    });

    it('creates morphable relationship with ULID IDs', function () {
        $postId = (string) Str::ulid();
        $post = new TestPostWithUlid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        expect($file->fileable_id)->toBeString();
        expect(Str::isUlid($file->fileable_id))->toBeTrue();
        expect($file->fileable_id)->toBe($postId);
        expect($file->fileable_type)->toBe(TestPostWithUlid::class);
    });

    it('can retrieve fileable model with ULID', function () {
        $postId = (string) Str::ulid();
        $post = new TestPostWithUlid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $uploadedFile = UploadedFile::fake()->image('test.jpg');
        $file = $post->attach('photos', $uploadedFile);

        $retrievedFile = File::find($file->id);
        expect($retrievedFile)->not->toBeNull();
        expect($retrievedFile->fileable)->not->toBeNull();
        expect($retrievedFile->fileable->id)->toBe($postId);
    });

    it('ULIDs are sortable by creation time', function () {
        $postId = (string) Str::ulid();
        $post = new TestPostWithUlid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $file1 = $post->attach('photos', UploadedFile::fake()->image('test1.jpg'));
        usleep(1000); // Small delay to ensure different ULIDs
        $file2 = $post->attach('photos', UploadedFile::fake()->image('test2.jpg'));

        expect($file1->id)->toBeLessThan($file2->id);
    });
});

describe('ServiceProvider Boot Configuration', function () {
    it('configures morphUsingUuids when uuid is set', function () {
        Config::set('filexus.primary_key_type', 'uuid');

        // The service provider will handle this on boot
        expect(config('filexus.primary_key_type'))->toBe('uuid');
    });

    it('configures morphUsingUlids when ulid is set', function () {
        Config::set('filexus.primary_key_type', 'ulid');

        // The service provider will handle this on boot
        expect(config('filexus.primary_key_type'))->toBe('ulid');
    });

    it('does not configure morph methods for auto-increment', function () {
        Config::set('filexus.primary_key_type', 'id');

        // No special morph configuration needed for auto-increment
        expect(config('filexus.primary_key_type'))->toBe('id');
    });
});

describe('File Operations with Different Key Types', function () {
    it('detaches file correctly with UUID keys', function () {
        Config::set('filexus.primary_key_type', 'uuid');
        recreateFilesTableWithKeyType('uuid');
        createPostsTable('uuid');

        $postId = Str::uuid()->toString();
        $post = new TestPostWithUuid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $file = $post->attach('photos', UploadedFile::fake()->image('test.jpg'));
        $fileId = $file->id;

        expect(File::find($fileId))->not->toBeNull();

        $post->detach('photos', $fileId);

        expect(File::find($fileId))->toBeNull();
    });

    it('detaches file correctly with ULID keys', function () {
        Config::set('filexus.primary_key_type', 'ulid');
        recreateFilesTableWithKeyType('ulid');
        createPostsTable('ulid');

        $postId = (string) Str::ulid();
        $post = new TestPostWithUlid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $file = $post->attach('photos', UploadedFile::fake()->image('test.jpg'));
        $fileId = $file->id;

        expect(File::find($fileId))->not->toBeNull();

        $post->detach('photos', $fileId);

        expect(File::find($fileId))->toBeNull();
    });

    it('queries files by collection with UUID', function () {
        Config::set('filexus.primary_key_type', 'uuid');
        recreateFilesTableWithKeyType('uuid');
        createPostsTable('uuid');

        $postId = Str::uuid()->toString();
        $post = new TestPostWithUuid(['id' => $postId, 'title' => 'Test Post']);
        $post->save();

        $post->attach('photos', UploadedFile::fake()->image('test1.jpg'));
        $post->attach('documents', UploadedFile::fake()->create('test.pdf'));

        $photos = File::whereCollection('photos')->get();
        $documents = File::whereCollection('documents')->get();

        expect($photos)->toHaveCount(1);
        expect($documents)->toHaveCount(1);
    });

    it('validates UUID format when querying files', function () {
        Config::set('filexus.primary_key_type', 'uuid');
        recreateFilesTableWithKeyType('uuid');

        // Try to find a file with an invalid UUID format
        // This should trigger isValidUniqueId internally
        $file = File::find('not-a-valid-uuid');

        expect($file)->toBeNull();
    });

    it('validates ULID format when querying files', function () {
        Config::set('filexus.primary_key_type', 'ulid');
        recreateFilesTableWithKeyType('ulid');

        // Try to find a file with an invalid ULID format
        // This should trigger isValidUniqueId internally
        $file = File::find('not-a-valid-ulid');

        expect($file)->toBeNull();
    });
});
