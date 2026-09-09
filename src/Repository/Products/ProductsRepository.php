<?php

namespace App\Repository\Products;

use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\StringUtil;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class ProductsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($registry, Products::class);
    }

    public function getProductsList(bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "products.id",
            "products.name",
            "products.description",
            "products.basePrice",
            "products.publicPrice",
            "products.active"
        ]);
        $query = $this->createQueryBuilder('products');

        if ($isCount) {
            $query->select("COUNT(products.id) as total");
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }

        $query
            ->join("products.domain", "domain")
            ->andWhere('domain.id = :domain')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($this->getDomainData->getDomainCache()));

        $query = $this->listDataTable->searchByAllColumns(
            $query
        );

        $query = $this->listDataTable->preGetQuery($query, $isCount);

        if ($isCount) {
            return $query->getQuery()->getSingleResult();
        }

        return $query->getQuery()->getResult();
    }

    public function getProductsToECommerce(Domains $domains): array
    {
        $qb = $this->createQueryBuilder('products');

        $qb
            ->select([
                "products.id",
                "products.name",
                "products.description",
                "products.publicPrice",
                "images.image"
            ])
            ->join("products.domain", "domain")
            ->join("products.imagesProducts", "images")
            ->andWhere('domain.id = :domain')
            ->andWhere('products.active = true')
            ->andWhere('images.orderColumn = (
                    SELECT MIN(img2.orderColumn)
                    FROM App\Entity\Products\Others\ImagesProducts img2
                    WHERE img2.product = products.id and img2.active = true
                )')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domains->getId()))
            ->orderBy('products.name', 'ASC')
            ->setMaxResults(10)
            ->groupBy('products.id, products.name, products.description, products.publicPrice, images.image');

        return $qb->getQuery()->getArrayResult();
    }

    public function getProductToDetailInECommerce(Products $products): array
    {
        $qb = $this->createQueryBuilder('products');

        $qb
            ->select([
                "images.image", "productColor.id AS colorId", "productColor.variantGroupId AS variantGroupId"
            ])
            ->join("products.domain", "domain")
            ->join("products.imagesProducts", "images")
            ->leftJoin("images.productColor", "productColor")
            ->andWhere('domain.id = :domain')
            ->andWhere('products.active = true')
            ->andWhere('images.active = true')
            ->andWhere('products.id = :product')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($this->getDomainData->getDomainCache()))
            ->setParameter('product', UUIDUtil::convertIdToSearch($products))

            ->orderBy('productColor.orderColumn', 'ASC')
            ->addOrderBy('images.orderColumn', 'ASC');

        return $qb->getQuery()->getArrayResult();
    }

    public function getPublicHomeList(Domains $domain, int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->select([
                'p.id', 'p.name', 'p.description', 'p.publicPrice', 'p.stock', 'MIN(img.image) AS image',
            ])
            ->join('p.domain', 'd')
            ->leftJoin('p.imagesProducts', 'img', 'WITH', 'img.active = :active')
            ->where('d.id = :domain')
            ->andWhere('p.active = :active')
            ->andWhere('p.stock IS NULL OR p.stock > 0')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->groupBy('p.id, p.name, p.description, p.publicPrice, p.stock')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function searchPublic(
        Domains $domain,
        ?array $allowedProductIds,
        ?string $categoryId,
        ?string $search,
        ?int $minPrice,
        ?int $maxPrice,
        int $page,
        int $limit,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->select([
                'p.id', 'p.name', 'p.description', 'p.publicPrice', 'p.stock',
                'MIN(img.image) AS image',
            ])
            ->join('p.domain', 'd')
            ->leftJoin('p.imagesProducts', 'img', 'WITH', 'img.active = :active')
            ->where('d.id = :domain')
            ->andWhere('p.active = :active')

            ->andWhere('p.stock IS NULL OR p.stock > 0')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->groupBy('p.id, p.name, p.description, p.publicPrice, p.stock')
            ->orderBy('p.name', 'ASC');

        if ($allowedProductIds !== null) {
            $binaryIds = array_map(
                fn (string $id) => UUIDUtil::convertIdToSearch($id),
                $allowedProductIds
            );
            $qb->andWhere('p.id IN (:productIds)')
               ->setParameter('productIds', $binaryIds);
        }

        if ($categoryId !== null) {
            $qb->join('App\Entity\Products\Categories\ProductsCategories', 'pc', 'WITH', 'pc.product = p.id AND pc.active = :active')
               ->join('pc.category', 'cat')
               ->andWhere('cat.id = :category')
               ->setParameter('category', UUIDUtil::convertIdToSearch($categoryId));
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($minPrice !== null) {
            $qb->andWhere('p.publicPrice >= :minPrice')
               ->setParameter('minPrice', $minPrice * 100);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('p.publicPrice <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice * 100);
        }

        $total = (clone $qb)
            ->select('COUNT(DISTINCT p.id) AS cnt')
            ->resetDQLPart('groupBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return ['items' => $items, 'total' => (int) $total];
    }

    public function invalidatePublicHomeCache(Domains $domain): void
    {
        $this->cache->delete("store_home_{$domain->getId()}");
    }
}
