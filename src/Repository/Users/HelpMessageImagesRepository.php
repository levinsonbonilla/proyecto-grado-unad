<?php

namespace App\Repository\Users;

use App\Entity\Users\HelpMessageImages;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HelpMessageImagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HelpMessageImages::class);
    }

    public function getGroupedByMessageIds(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        $binaryIds = array_map(fn(string $id) => UUIDUtil::convertIdToSearch($id), $messageIds);

        $rows = $this->createQueryBuilder('hmi')
            ->join('hmi.helpMessage', 'hm')
            ->select(['hm.id as messageId', 'hmi.imageUrl'])
            ->where('hm.id IN (:ids)')
            ->andWhere('hmi.active = :active')
            ->setParameter('ids', $binaryIds)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['messageId']][] = $row['imageUrl'];
        }

        return $grouped;
    }
}
