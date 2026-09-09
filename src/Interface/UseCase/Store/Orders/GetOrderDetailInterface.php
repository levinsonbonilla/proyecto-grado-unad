<?php

namespace App\Interface\UseCase\Store\Orders;

use App\Entity\Products\Orders\Orders;

interface GetOrderDetailInterface
{
    public function handler(Orders $order): array;
}
