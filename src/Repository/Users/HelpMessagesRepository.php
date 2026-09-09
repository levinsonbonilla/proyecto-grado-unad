<?php

namespace App\Repository\Users;

use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

class HelpMessagesRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheItemPoolInterface $cache,
        private readonly ListDataTableInterface $listDataTable
    ) {
        parent::__construct($registry, HelpMessages::class);
    }

    public function getInbox(Users $user, bool $isCount = false, ?string $filterUserId = null, ?bool $filterIsRead = null): ?array
    {
        $query = $this->createQueryBuilder('hm')
            ->join('hm.toUser', 'tu')
            ->join('hm.fromUser', 'fu')
            ->where('tu.id = :userId')
            ->andWhere('hm.active = :active')
            ->andWhere('hm.parentMessage IS NULL')
            ->setParameter('userId', UUIDUtil::convertIdToSearch($user->getId()))
            ->setParameter('active', true)
            ->orderBy('hm.isRead', 'ASC')
            ->addOrderBy('hm.createdAt', 'DESC');

        if ($filterUserId !== null) {
            $query->andWhere('fu.id = :filterUserId')
                ->setParameter('filterUserId', UUIDUtil::convertIdToSearch($filterUserId));
        }

        if ($filterIsRead !== null) {
            $query->andWhere('hm.isRead = :filterIsRead')
                ->setParameter('filterIsRead', $filterIsRead);
        }

        if ($isCount) {
            $query->select('COUNT(hm.id) as total');
        } else {
            $query->select([
                'hm.id', 'hm.message', 'hm.subject', 'hm.isRead', 'hm.createdAt',
                'fu.id as fromUserId', 'fu.name as fromUserName', 'fu.lastName as fromUserLastName',
            ]);
        }

        $query = $this->listDataTable->searchByAllColumns($query);
        $query = $this->listDataTable->preGetQuery($query, $isCount);

        return $isCount
            ? $query->getQuery()->getSingleResult()
            : $query->getQuery()->getResult();
    }

    public function getSenders(Users $user): array
    {
        return $this->createQueryBuilder('hm')
            ->join('hm.fromUser', 'fu')
            ->join('hm.toUser', 'tu')
            ->select(['fu.id', 'fu.name', 'fu.lastName'])
            ->where('tu.id = :userId')
            ->andWhere('hm.active = :active')
            ->setParameter('userId', UUIDUtil::convertIdToSearch($user->getId()))
            ->setParameter('active', true)
            ->groupBy('fu.id')
            ->getQuery()
            ->getResult();
    }

    public function getSent(Users $user, bool $isCount = false): ?array
    {
        $query = $this->createQueryBuilder('hm')
            ->join('hm.fromUser', 'fu')
            ->join('hm.toUser', 'tu')
            ->where('fu.id = :userId')
            ->andWhere('hm.active = :active')
            ->andWhere('hm.parentMessage IS NULL')
            ->setParameter('userId', UUIDUtil::convertIdToSearch($user->getId()))
            ->setParameter('active', true)
            ->orderBy('hm.createdAt', 'DESC');

        if ($isCount) {
            $query->select('COUNT(hm.id) as total');
        } else {
            $query->select([
                'hm.id', 'hm.message', 'hm.subject', 'hm.isRead', 'hm.createdAt',
                'tu.id as toUserId', 'tu.name as toUserName', 'tu.lastName as toUserLastName',
            ]);
        }

        $query = $this->listDataTable->searchByAllColumns($query);
        $query = $this->listDataTable->preGetQuery($query, $isCount);

        return $isCount
            ? $query->getQuery()->getSingleResult()
            : $query->getQuery()->getResult();
    }

    public function getThread(HelpMessages $rootMessage): array
    {
        $qb = $this->createQueryBuilder('hm');
        return $qb
            ->leftJoin('hm.fromUser', 'fu')
            ->leftJoin('hm.toUser', 'tu')
            ->select([
                'hm.id', 'hm.message', 'hm.isRead', 'hm.createdAt',
                'fu.id as fromUserId', 'fu.name as fromUserName', 'fu.lastName as fromUserLastName',
                'tu.id as toUserId', 'tu.name as toUserName', 'tu.lastName as toUserLastName',
            ])
            ->where($qb->expr()->orX('hm.id = :rootId', 'hm.parentMessage = :rootId'))
            ->andWhere('hm.active = :active')
            ->setParameter('rootId', UUIDUtil::convertIdToSearch($rootMessage->getId()))
            ->setParameter('active', true)
            ->orderBy('hm.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnread(Users $user): int
    {
        return (int) $this->createQueryBuilder('hm')
            ->select('COUNT(hm.id)')
            ->join('hm.toUser', 'u')
            ->where('u.id = :userId')
            ->andWhere('hm.isRead = :isRead')
            ->andWhere('hm.active = :active')
            ->setParameter('userId', UUIDUtil::convertIdToSearch($user->getId()))
            ->setParameter('isRead', false)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
