<?php

namespace App\Repository\Tenants\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Statistics\Statistics;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class StatisticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statistics::class);
    }

    public function getKpiSummary(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return [
            'totalVisits'    => $this->getTotalVisits($domain, $from, $to),
            'uniqueVisitors' => $this->getUniqueVisitors($domain, $from, $to),
            'uniqueSessions' => $this->getUniqueSessions($domain, $from, $to),
            'countriesCount' => $this->getCountriesCount($domain, $from, $to),
        ];
    }

    public function getTotalVisits(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->baseQuery($domain, $from, $to)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUniqueVisitors(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->baseQuery($domain, $from, $to)
            ->select('COUNT(DISTINCT s.ip)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUniqueSessions(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->baseQuery($domain, $from, $to)
            ->select('COUNT(DISTINCT s.sessionId)')
            ->andWhere('s.sessionId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getVisitsByDay(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("DATE(s.createdAt) as date, COUNT(s.id) as visits")
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getVisitsByCountry(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("s.country as country, s.countryIsoCode as id, COUNT(s.id) as visits")
            ->andWhere('s.countryIsoCode IS NOT NULL')
            ->groupBy('s.countryIsoCode, s.country')
            ->orderBy('visits', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopBrowsers(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("COALESCE(s.browser, 'Unknown') as browser, COUNT(s.id) as visits")
            ->groupBy('s.browser')
            ->orderBy('visits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopDevices(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("COALESCE(s.device, 'unknown') as device, COUNT(s.id) as visits")
            ->groupBy('s.device')
            ->orderBy('visits', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopOperativeSystems(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 8): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("COALESCE(s.operativeSystem, 'Unknown') as os, COUNT(s.id) as visits")
            ->groupBy('s.operativeSystem')
            ->orderBy('visits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopPages(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("s.page as page, COUNT(s.id) as visits")
            ->andWhere('s.page IS NOT NULL')
            ->groupBy('s.page')
            ->orderBy('visits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopReferrers(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("COALESCE(s.referrerDomain, 'Direct') as referrer, COUNT(s.id) as visits")
            ->groupBy('s.referrerDomain')
            ->orderBy('visits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopCampaigns(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("
                COALESCE(s.utmCampaign, '(none)') as campaign,
                COALESCE(s.utmSource, '(none)') as source,
                COALESCE(s.utmMedium, '(none)') as medium,
                COUNT(s.id) as visits
            ")
            ->andWhere('s.utmCampaign IS NOT NULL OR s.utmSource IS NOT NULL')
            ->groupBy('s.utmCampaign, s.utmSource, s.utmMedium')
            ->orderBy('visits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getVisitorsByAuthStatus(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseQuery($domain, $from, $to)
            ->select("CASE WHEN s.user IS NULL THEN 'guest' ELSE 'registered' END as status, COUNT(s.id) as visits")
            ->groupBy('status')
            ->getQuery()
            ->getArrayResult();
    }

    public function getDistinctIdentifiers(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $pagePattern = null): array
    {
        $query = $this->baseQuery($domain, $from, $to)
            ->select('IDENTITY(s.user) as userId', 's.sessionId as sessionId')
            ->distinct();

        if ($pagePattern !== null) {
            $query->andWhere('s.page LIKE :pagePattern')->setParameter('pagePattern', $pagePattern);
        }

        $rows = $query->getQuery()->getArrayResult();

        $identifiers = array_map(
            static fn(array $row): ?string => $row['userId'] !== null
                ? UUIDUtil::convertBinaryToUuid($row['userId'])
                : $row['sessionId'],
            $rows
        );

        return array_values(array_unique(array_filter($identifiers)));
    }

    private function getCountriesCount(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->baseQuery($domain, $from, $to)
            ->select('COUNT(DISTINCT s.country)')
            ->andWhere('s.country IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function baseQuery(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.domain = :domain')
            ->andWhere('s.createdAt >= :from')
            ->andWhere('s.createdAt <= :to')
            ->andWhere('s.active = true')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('from', $from)
            ->setParameter('to', $to);
    }
}
