<?php

namespace App\Service\Products;

interface GalleryImageGrouperInterface
{

    public function group(array $images): array;
}
