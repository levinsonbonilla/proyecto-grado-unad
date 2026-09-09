<?php

namespace App\Interface\UseCase\Store\Cart;

interface RemoveFromCartInterface
{
    public function handler(string $cartItemId): array;
}
