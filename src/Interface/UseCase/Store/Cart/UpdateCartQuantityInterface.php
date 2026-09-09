<?php

namespace App\Interface\UseCase\Store\Cart;

interface UpdateCartQuantityInterface
{
    public function handler(string $cartItemId, int $quantity): array;
}
