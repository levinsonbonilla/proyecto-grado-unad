<?php

namespace App\Repository\Users;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UsersRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, Users::class);
    }

    #[\Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Users) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findActiveSuperAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.active = :active')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('active', true)
            ->setParameter('role', '%ROLE_SUPER_ADMIN%')
            ->getQuery()
            ->getResult();
    }

    public function findSuperAdminsByDomain(Domains $domain): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.userDomains', 'ud')
            ->where('ud.domain = :domain')
            ->andWhere('ud.active = :active')
            ->andWhere('u.active = :active')
            ->andWhere('ud.roles LIKE :role')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->setParameter('role', '%ROLE_SUPER_ADMIN%')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByDomain(Domains $domain, ?Users $exclude = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->join('u.userDomains', 'ud')
            ->where('ud.domain = :domain')
            ->andWhere('ud.active = :active')
            ->andWhere('u.active = :active')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true);

        if ($exclude) {
            $qb->andWhere('u != :exclude')
               ->setParameter('exclude', $exclude);
        }

        return $qb->getQuery()->getResult();
    }
}
