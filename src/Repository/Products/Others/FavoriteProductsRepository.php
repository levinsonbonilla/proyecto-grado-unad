<?php

namespace App\Repository\Products\Others;

use App\Entity\Products\Others\FavoriteProducts;
use App\Entity\Users\Users;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoriteProductsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteProducts::class);
    }

    public function getActiveProductIdsForUser(Users $user): array
    {
        return array_map(
            'strval',
            $this->createQueryBuilder('f')
                ->select('p.id')
                ->join('f.product', 'p')
                ->where('f.user = :user')
                ->andWhere('f.active = :active')
                ->setParameter('user', UUIDUtil::convertIdToSearch($user))
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleColumnResult()
        );
    }

    public function getActiveForUser(Users $user): array
    {
        return $this->createQueryBuilder('f')
            ->select([
                'p.id', 'p.name', 'p.publicPrice', 'p.stock',
                'MIN(img.image) AS image', 'f.id AS favoriteId',
            ])
            ->join('f.product', 'p')
            ->leftJoin('p.imagesProducts', 'img', 'WITH', 'img.active = :active')
            ->where('f.user = :user')
            ->andWhere('f.active = :active')
            ->andWhere('p.active = :active')
            ->setParameter('user', UUIDUtil::convertIdToSearch($user))
            ->setParameter('active', true)
            ->groupBy('p.id, p.name, p.publicPrice, p.stock, f.id')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

}
