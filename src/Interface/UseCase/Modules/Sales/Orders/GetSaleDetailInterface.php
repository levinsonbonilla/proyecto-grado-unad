<?php

namespace App\Interface\UseCase\Modules\Sales\Orders;

use App\Entity\Products\Orders\Orders;

interface GetSaleDetailInterface
{
    public function handler(Orders $order): array;
}
