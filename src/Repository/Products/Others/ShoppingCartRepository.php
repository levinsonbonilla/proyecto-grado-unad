<?php

namespace App\Repository\Products\Others;

use App\Entity\Products\Others\ShoppingCart;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShoppingCartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingCart::class);
    }

    public function getTotalQuantityForUser(Users $user, Domains $domain): int
    {
        return (int) ($this->createQueryBuilder('c')
            ->select('SUM(c.quantity)')
            ->join('c.product', 'p')
            ->where('c.user = :user')
            ->andWhere('c.active = :active')
            ->andWhere('p.domain = :domain')
            ->setParameter('user', UUIDUtil::convertIdToSearch($user))
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function findActiveForUserInDomain(Users $user, Domains $domain): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.product', 'p')
            ->where('c.user = :user')
            ->andWhere('c.active = :active')
            ->andWhere('p.domain = :domain')
            ->setParameter('user', UUIDUtil::convertIdToSearch($user))
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function getActiveItemsForUser(Users $user, Domains $domain): array
    {
        return $this->createQueryBuilder('c')
            ->select([
                'c.id AS cartItemId',
                'p.id AS productId', 'p.name', 'p.publicPrice', 'MIN(img.image) AS image',
                'c.quantity', 'c.addedAt',
                'pc.id AS productColorId', 'col.name AS colorName', 'col.hexCode AS colorHexCode', 'pc.image AS colorImage',
                'md.name AS medidaName',
                'p.stock AS productStock', 'pc.stock AS blockStock',
            ])
            ->join('c.product', 'p')
            ->leftJoin('p.imagesProducts', 'img', 'WITH', 'img.active = :active')
            ->leftJoin('c.productColor', 'pc')
            ->leftJoin('pc.color', 'col')
            ->leftJoin('pc.medida', 'md')
            ->where('c.user = :user')
            ->andWhere('c.active = :active')
            ->andWhere('p.domain = :domain')
            ->setParameter('user', UUIDUtil::convertIdToSearch($user))
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->groupBy('c.id, p.id, p.name, p.publicPrice, c.quantity, c.addedAt, pc.id, col.name, col.hexCode, pc.image, md.name, p.stock, pc.stock')
            ->getQuery()
            ->getArrayResult();
    }
}
