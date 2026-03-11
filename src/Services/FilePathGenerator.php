<?php

declare(strict_types=1);

namespace Filexus\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Service class for generating file storage paths.
 *
 * Pattern: /{model}/{id}/{collection}/{uuid}.{ext}
 * Example: /posts/15/gallery/550e8400-e29b-41d4-a716-446655440000.jpg
 */
class FilePathGenerator
{
    /**
     * Generate a storage path for the uploaded file.
     *
     * @param Model $model The model the file will be attached to
     * @param string $collection The collection name
     * @param UploadedFile $file The uploaded file
     * @return string The generated path
     */
    public function generate(Model $model, string $collection, UploadedFile $file): string
    {
        $modelName = $this->getModelName($model);
        $modelId = $model->getKey();
        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();

        return sprintf(
            '%s/%s/%s/%s.%s',
            $modelName,
            $modelId,
            $collection,
            $uuid,
            $extension
        );
    }

    /**
     * Get a normalized model name for the path.
     *
     * @param Model $model
     * @return string
     */
    protected function getModelName(Model $model): string
    {
        $className = class_basename($model);
        return Str::plural(Str::lower($className));
    }
}
