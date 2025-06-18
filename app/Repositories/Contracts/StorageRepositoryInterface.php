<?php

namespace App\Repositories\Contracts;

interface StorageRepositoryInterface
{
    public function storeFile($file): string;

    public function deleteFile(string $path): bool;

    public function getFileUrl(string $path): string;
}
