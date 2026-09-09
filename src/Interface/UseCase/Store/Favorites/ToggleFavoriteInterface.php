<?php

namespace App\Interface\UseCase\Store\Favorites;

interface ToggleFavoriteInterface
{
    public function handler(string $productId): array;
}
