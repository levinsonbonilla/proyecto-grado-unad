<?php

namespace App\Interface\UseCase\Store\Cart;

interface AddToCartInterface
{
    public function handler(string $productId, int $quantity = 1, ?string $colorId = null): array;
}
