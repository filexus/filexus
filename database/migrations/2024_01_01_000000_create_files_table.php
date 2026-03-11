<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            // Primary key based on configuration
            $keyType = config('filexus.primary_key_type', 'id');

            match ($keyType) {
                'uuid' => $table->uuid('id')->primary(),
                'ulid' => $table->ulid('id')->primary(),
                default => $table->id(),
            };

            // Storage information
            $table->string('disk', 50);
            $table->string('path');
            $table->string('collection', 100);

            // Polymorphic relationship - need to match the key type
            if ($keyType === 'uuid') {
                $table->uuidMorphs('fileable');
            } elseif ($keyType === 'ulid') {
                $table->ulidMorphs('fileable');
            } else {
                $table->morphs('fileable');
            }

            // File metadata
            $table->string('original_name');
            $table->string('mime', 100);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size'); // bytes
            $table->string('hash', 64); // SHA256

            // Additional metadata (JSON)
            $table->json('metadata')->nullable();

            // Expiration support
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Indexes for performance (morphs already creates fileable index)
            $table->index('collection');
            $table->index('hash');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
