<?php

namespace App\Repository\Tenants\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Statistics\StatisticsEvents;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class StatisticsEventsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatisticsEvents::class);
    }

    public function getTopEvents(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("e.eventName as event, COUNT(e.id) as total")
            ->groupBy('e.eventName')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getEventsByDay(Domains $domain, string $eventName, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->andWhere('e.eventName = :eventName')
            ->setParameter('eventName', $eventName)
            ->select("DATE(e.createdAt) as date, COUNT(e.id) as total")
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getDistinctIdentifiersForEvent(Domains $domain, string $eventName, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {

        $rows = $this->baseQuery($domain, $from, $to)
            ->andWhere('e.eventName = :eventName')
            ->setParameter('eventName', $eventName)
            ->select('IDENTITY(e.user) as userId', 'e.sessionId as sessionId')
            ->distinct()
            ->getQuery()
            ->getArrayResult();

        $identifiers = array_map(
            static fn(array $row): ?string => $row['userId'] !== null
                ? UUIDUtil::convertBinaryToUuid($row['userId'])
                : $row['sessionId'],
            $rows
        );

        return array_values(array_unique(array_filter($identifiers)));
    }

    private function baseQuery(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.domain = :domain')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->andWhere('e.active = true')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('from', $from)
            ->setParameter('to', $to);
    }
}
