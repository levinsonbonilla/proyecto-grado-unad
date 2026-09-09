<?php

namespace App\Util;

use App\Interface\Configuration\S3ManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageUtil
{
    public function __construct(
        private readonly S3ManagerInterface $s3Manager,
    ) {
    }

    public function addImage(string $dir, ?UploadedFile $file = null): ?string
    {
        if (empty($file)) {
            return $file;
        }

        $s3Image = $this->s3Manager->create($file, $dir);
        if (empty($s3Image)) {
            throw new \Exception("Ocurrió un error durante la creación de una imagen", 500);
        }

        return $s3Image;
    }
}
