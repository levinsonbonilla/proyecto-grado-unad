<?php

namespace App\Repository\Products\Orders;

use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\PaymentTransactions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class PaymentTransactionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentTransactions::class);
    }

    
    public function findLatestByOrder(Orders $order): ?PaymentTransactions
    {
        return $this->findOneBy(['orders' => $order, 'active' => true], ['createdAt' => 'DESC']);
    }

    public function findByGatewayReference(string $gatewayReference): ?PaymentTransactions
    {
        return $this->findOneBy(['gatewayReference' => $gatewayReference]);
    }
}
