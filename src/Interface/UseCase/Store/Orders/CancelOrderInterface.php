<?php

namespace App\Interface\UseCase\Store\Orders;

use App\Entity\Products\Orders\Orders;

interface CancelOrderInterface
{
    public function handler(Orders $order): array;
}
