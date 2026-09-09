<?php

namespace App\Repository\Products\Others;

use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Products;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImagesProductsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImagesProducts::class);
    }

    public function getActiveByProductOrdered(Products $product): array
    {
        return $this->createQueryBuilder('img')
            ->select(['img.image', 'img.orderColumn', 'pc.id AS colorId', 'pc.variantGroupId AS variantGroupId'])
            ->leftJoin('img.productColor', 'pc')
            ->where('IDENTITY(img.product) = :productId')
            ->andWhere('img.active = :active')
            ->setParameter('productId', $product->getId(), 'uuid')
            ->setParameter('active', true)

            ->orderBy('pc.orderColumn', 'ASC')
            ->addOrderBy('img.orderColumn', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
