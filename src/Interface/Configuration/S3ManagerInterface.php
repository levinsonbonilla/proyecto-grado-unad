<?php

namespace App\Interface\Configuration;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface S3ManagerInterface
{
    public function create(UploadedFile $file, string $path): ?string;

    public function delete(string $file): void;
}
