<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Messages\AvailableUsersInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class AvailableUsersUseCase implements AvailableUsersInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UsersRepository $usersRepository,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {

            $currentUser  = $this->security->getUser();
            $domain       = $this->getDomainData->getDomainCache();
            $isSuperAdmin = $this->security->isGranted('ROLE_SUPER_ADMIN');

            if ($isSuperAdmin) {
                $users = $this->usersRepository->findActiveByDomain($domain, $currentUser);
                $mode  = 'multi';
            } else {
                $users = $this->usersRepository->findSuperAdminsByDomain($domain);
                $mode  = count($users) === 1 ? 'auto' : 'single';
            }

            return [
                'mode'  => $mode,
                'users' => array_map(fn(Users $u) => [
                    'id'   => (string) $u->getId(),
                    'name' => $u->getName() . ' ' . $u->getLastName(),
                ], $users),
            ];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['mode' => 'auto', 'users' => []];
        }
    }
}
