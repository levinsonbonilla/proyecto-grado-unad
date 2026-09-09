<?php

namespace App\Repository\Products\Orders;

use App\Entity\Products\Orders\Orders;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\ListDataTableInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class OrdersRepository extends ServiceEntityRepository
{

    private const PAID_STATUSES = ['Procesando', 'Enviado', 'Entregado'];

    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
    ) {
        parent::__construct($registry, Orders::class);
    }

    public function findByUser(object $user, string $locale = 'es'): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select(['o', 's.name AS statusName'])
            ->join('o.status', 's')
            ->join('o.shippingCountry', 'co')
            ->join('o.shippingRegion', 'r')
            ->where('o.user = :user AND o.active = :active')
            ->setParameter('user', UUIDUtil::convertIdToSearch($user))
            ->setParameter('active', true)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (array $row) use ($locale): array {

            $order = $row[0];
            return [
                'id'          => $order->getId(),
                'totalAmount' => $order->getTotalAmount(),
                'createdAt'   => $order->getCreatedAt(),
                'statusName'  => $row['statusName'],
                'countryName' => $order->getShippingCountry()->getName($locale),
                'regionName'  => $order->getShippingRegion()->getName($locale),
            ];
        }, $rows);
    }

    public function findDetailById(string $orderId, object $user, string $locale = 'es'): ?array
    {

        $row = $this->createQueryBuilder('o')
            ->select(['o', 's.name AS statusName'])
            ->join('o.status', 's')
            ->join('o.shippingCountry', 'co')
            ->join('o.shippingRegion', 'r')
            ->join('o.shippingCity', 'ci')
            ->where('o.id = :id AND o.user = :user AND o.active = :active')
            ->setParameter('id', UUIDUtil::convertIdToSearch($orderId))
            ->setParameter('user', UUIDUtil::convertIdToSearch($user))
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();

        if ($row === null) {
            return null;
        }

        $order = $row[0];

        return [
            'id'              => $order->getId(),
            'totalAmount'     => $order->getTotalAmount(),
            'shippingAddress' => $order->getShippingAddress(),
            'createdAt'       => $order->getCreatedAt(),
            'trackingNumber'  => $order->getTrackingNumber(),
            'trackingCarrier' => $order->getTrackingCarrier(),
            'statusName'      => $row['statusName'],
            'countryName'     => $order->getShippingCountry()->getName($locale),
            'regionName'      => $order->getShippingRegion()->getName($locale),
            'cityNames'       => $order->getShippingCity()->getName($locale),
        ];
    }

    public function getList(Domains $domain, bool $isCount = false, ?string $statusFilter = null): array
    {
        $this->listDataTable->setRealColumns([
            'o.id',
            'o.totalAmount',
            'o.createdAt',
            'o.trackingNumber',
            's.name AS statusName',
            'u.name AS customerName',
            'u.email AS customerEmail',
        ]);

        $query = $this->createQueryBuilder('o')
            ->join('o.user', 'u')
            ->join('o.status', 's')
            ->andWhere('o.active = :active')
            ->andWhere('o.id IN (
                SELECT IDENTITY(op.orders) FROM App\Entity\Products\Orders\OrdersProducts op
                JOIN op.product p
                WHERE p.domain = :domain AND op.active = true
            )')
            ->setParameter('active', true)
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain));

        if (!empty($statusFilter)) {
            $query->andWhere('s.name = :statusFilter')->setParameter('statusFilter', $statusFilter);
        }

        if ($isCount) {
            $query->select('COUNT(o.id) as total');
        } else {
            $query->select($this->listDataTable->getRealColumns())
                ->orderBy('o.createdAt', 'DESC');
        }

        $query = $this->listDataTable->searchByAllColumns($query);
        $query = $this->listDataTable->preGetQuery($query, $isCount);

        return $isCount ? $query->getQuery()->getSingleResult() : $query->getQuery()->getResult();
    }

    public function getAdminDetail(Orders $order, string $locale = 'es'): ?array
    {

        $row = $this->createQueryBuilder('o')
            ->select([
                'o', 's.name AS statusName',
                'u.name AS customerName', 'u.email AS customerEmail',
            ])
            ->join('o.status', 's')
            ->join('o.shippingCountry', 'co')
            ->join('o.shippingRegion', 'r')
            ->join('o.shippingCity', 'ci')
            ->join('o.user', 'u')
            ->where('o.id = :id')
            ->setParameter('id', UUIDUtil::convertIdToSearch($order))
            ->getQuery()
            ->getOneOrNullResult();

        if ($row === null) {
            return null;
        }

        $entity = $row[0];

        return [
            'id'              => $entity->getId(),
            'totalAmount'     => $entity->getTotalAmount(),
            'shippingAddress' => $entity->getShippingAddress(),
            'createdAt'       => $entity->getCreatedAt(),
            'trackingNumber'  => $entity->getTrackingNumber(),
            'trackingCarrier' => $entity->getTrackingCarrier(),
            'statusName'      => $row['statusName'],
            'countryName'     => $entity->getShippingCountry()->getName($locale),
            'regionName'      => $entity->getShippingRegion()->getName($locale),
            'cityNames'       => $entity->getShippingCity()->getName($locale),
            'customerName'    => $row['customerName'],
            'customerEmail'   => $row['customerEmail'],
        ];
    }

    public function getSalesKpiSummary(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return [
            'totalVentas'           => $this->getTotalSalesAmount($domain, $from, $to),
            'pedidosPendientesPago' => $this->getOrdersCountByStatus($domain, $from, $to, 'Pendiente'),
            'pedidosPorEnviar'      => $this->getOrdersCountByStatus($domain, $from, $to, 'Procesando'),
            'ticketPromedio'        => $this->getAverageTicket($domain, $from, $to),
        ];
    }

    private function getTotalSalesAmount(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) ($this->baseDomainQuery($domain, $from, $to)
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->getQuery()
            ->getSingleScalarResult());
    }

    private function getOrdersCountByStatus(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, string $statusName): int
    {
        return (int) $this->baseDomainQuery($domain, $from, $to)
            ->select('COUNT(o.id)')
            ->andWhere('s.name = :statusName')
            ->setParameter('statusName', $statusName)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getAverageTicket(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        return (float) ($this->baseDomainQuery($domain, $from, $to)
            ->select('COALESCE(AVG(o.totalAmount), 0)')
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function getDistinctBuyerIds(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->baseDomainQuery($domain, $from, $to)
            ->select('DISTINCT IDENTITY(o.user) as userId')
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(static fn($id) => UUIDUtil::convertBinaryToUuid($id), $rows);
    }

    public function getSalesByDay(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseDomainQuery($domain, $from, $to)
            ->select("DATE(o.createdAt) as date, COUNT(o.id) as orders, COALESCE(SUM(o.totalAmount), 0) as revenue")
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getSalesByCountry(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $locale, int $limit = 10): array
    {
        $rows = $this->baseDomainQuery($domain, $from, $to)
            ->select('IDENTITY(o.shippingCountry) as countryId', 'COUNT(o.id) as orders', 'COALESCE(SUM(o.totalAmount), 0) as revenue')
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->groupBy('countryId')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(function (array $row) use ($locale): array {
            $country = $this->countriesRepository->find(UUIDUtil::convertBinaryToUuid($row['countryId']));
            return [
                'country' => $country?->getName($locale) ?? '—',
                'orders'  => (int) $row['orders'],
                'revenue' => (int) $row['revenue'],
            ];
        }, $rows);
    }

    public function getSalesByRegion(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $locale, int $limit = 10): array
    {
        $rows = $this->baseDomainQuery($domain, $from, $to)
            ->select('IDENTITY(o.shippingRegion) as regionId', 'COUNT(o.id) as orders', 'COALESCE(SUM(o.totalAmount), 0) as revenue')
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->groupBy('regionId')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(function (array $row) use ($locale): array {
            $region = $this->regionsRepository->find(UUIDUtil::convertBinaryToUuid($row['regionId']));
            return [
                'region'  => $region?->getName($locale) ?? '—',
                'orders'  => (int) $row['orders'],
                'revenue' => (int) $row['revenue'],
            ];
        }, $rows);
    }

    public function getSalesByCity(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $locale, int $limit = 10): array
    {
        $rows = $this->baseDomainQuery($domain, $from, $to)
            ->select('IDENTITY(o.shippingCity) as cityId', 'COUNT(o.id) as orders', 'COALESCE(SUM(o.totalAmount), 0) as revenue')
            ->andWhere('s.name IN (:paidStatuses)')
            ->setParameter('paidStatuses', self::PAID_STATUSES)
            ->groupBy('cityId')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(function (array $row) use ($locale): array {
            $city = $this->citiesRepository->find(UUIDUtil::convertBinaryToUuid($row['cityId']));
            return [
                'city'    => $city?->getName($locale) ?? '—',
                'orders'  => (int) $row['orders'],
                'revenue' => (int) $row['revenue'],
            ];
        }, $rows);
    }

    public function getAverageShippingTimeHours(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): ?float
    {

        $rows = $this->baseDomainQuery($domain, $from, $to)
            ->select('o.createdAt', 'o.updatedAt')
            ->andWhere('s.name IN (:shippedStatuses)')
            ->setParameter('shippedStatuses', ['Enviado', 'Entregado'])
            ->getQuery()
            ->getArrayResult();

        if (empty($rows)) {
            return null;
        }

        $totalHours = array_sum(array_map(
            static fn(array $row): float => ($row['updatedAt']->getTimestamp() - $row['createdAt']->getTimestamp()) / 3600,
            $rows
        ));

        return $totalHours / count($rows);
    }

    private function baseDomainQuery(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->join('o.status', 's')
            ->andWhere('o.active = :active')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt <= :to')
            ->andWhere('o.id IN (
                SELECT IDENTITY(op.orders) FROM App\Entity\Products\Orders\OrdersProducts op
                JOIN op.product p
                WHERE p.domain = :domain AND op.active = true
            )')
            ->setParameter('active', true)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain));
    }
}
