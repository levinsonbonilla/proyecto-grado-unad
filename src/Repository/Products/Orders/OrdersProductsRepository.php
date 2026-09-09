<?php

namespace App\Repository\Products\Orders;

use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\OrdersProducts;
use App\Entity\Tenants\Domains\Domains;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrdersProductsRepository extends ServiceEntityRepository
{
    private const PAID_STATUSES = ['Procesando', 'Enviado', 'Entregado'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrdersProducts::class);
    }

    public function getTopSellingProducts(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->createQueryBuilder('op')
            ->select('p.name as product', 'SUM(op.quantity) as unitsSold', 'COALESCE(SUM(op.totalPrice), 0) as revenue')
            ->join('op.product', 'p')
            ->join('op.orders', 'o')
            ->join('o.status', 's')
            ->andWhere('p.domain = :domain')
            ->andWhere('op.active = true')
            ->andWhere('o.active = true')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt <= :to')
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->groupBy('p.id, p.name')
            ->orderBy('unitsSold', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findByOrder(Orders $order): array
    {
        return $this->createQueryBuilder('op')
            ->select([
                'op.quantity', 'op.unitPrice', 'op.totalPrice',
                'p.name AS productName',
                'MIN(img.image) AS image',
                'col.name AS colorName', 'col.hexCode AS colorHexCode',
                'md.name AS medidaName',
            ])
            ->join('op.product', 'p')
            ->leftJoin('p.imagesProducts', 'img', 'WITH', 'img.active = :imgActive')
            ->leftJoin('op.productColor', 'pc')
            ->leftJoin('pc.color', 'col')
            ->leftJoin('pc.medida', 'md')
            ->where('IDENTITY(op.orders) = :orderId')
            ->andWhere('op.active = :active')
            ->setParameter('orderId', $order->getId(), 'uuid')
            ->setParameter('active', true)
            ->setParameter('imgActive', true)
            ->groupBy('op.quantity, op.unitPrice, op.totalPrice, p.name, col.name, col.hexCode, md.name')
            ->getQuery()
            ->getArrayResult();
    }
}
