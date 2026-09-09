<?php

namespace App\Interface\UseCase\Store\Orders;

use App\Entity\Products\Orders\Orders;

interface RetractionOrderInterface
{
    public function handler(Orders $order): array;
}
