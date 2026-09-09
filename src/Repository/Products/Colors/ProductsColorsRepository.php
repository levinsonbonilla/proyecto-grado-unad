<?php

namespace App\Repository\Products\Colors;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductsColorsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductsColors::class);
    }

    public function getActiveByProduct(Products $product): array
    {
        return $this->createQueryBuilder('pc')
            ->select([
                'pc.id', 'pc.stock', 'pc.image', 'pc.orderColumn',
                'c.id AS colorId', 'c.name AS colorName', 'c.hexCode',
                'm.id AS medidaId', 'm.name AS medidaName',
            ])
            ->leftJoin('pc.color', 'c')
            ->leftJoin('pc.medida', 'm')
            ->where('IDENTITY(pc.product) = :productId')
            ->andWhere('pc.active = :active')
            ->andWhere('c.id IS NULL OR c.active = :active')
            ->andWhere('m.id IS NULL OR m.active = :active')
            ->andWhere('c.id IS NOT NULL OR m.id IS NOT NULL')
            ->setParameter('productId', $product->getId(), 'uuid')
            ->setParameter('active', true)

            ->orderBy('pc.orderColumn', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
