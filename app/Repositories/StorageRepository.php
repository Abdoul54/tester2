<?php

namespace App\Repositories;

use App\Repositories\Contracts\StorageRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StorageRepository implements StorageRepositoryInterface
{
    public function storeFile($file): string
    {
        // Generate a unique filename
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        // Store file in MinIO using 's3' disk (which is configured for MinIO)
        $stored = Storage::disk('s3')->putFileAs('posts', $file, $filename);

        if (!$stored) {
            throw new \Exception('Failed to store image in MinIO');
        }

        return $stored;
    }

    public function deleteFile(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    public function getFileUrl(string $path): string
    {
        try {
            return Storage::disk('s3')->url($path);
        } catch (\Exception $e) {
            Log::warning('Failed to generate image URL', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);

            return '';
        }
    }
}
