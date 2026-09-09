<?php

namespace App\Security;

use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\PrincipalDomainAdminAccessInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly EntityManagerInterface $entityManager,
        private readonly PrincipalDomainAdminAccessInterface $principalDomainAdminAccess,
    ) {}
    public function checkPreAuth(UserInterface $user): void
    {
        if (!is_object($user)) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Tu cuenta está desactivada.');
        }

        if (!$user->isValidatedEmail()) {
            throw new CustomUserMessageAccountStatusException('Debes verificar tu correo electrónico.');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $currentDomainId = (string) $this->getDomainData->getDomainCache()->getId();
        $domains = $user->getDomainsActives()
            ->filter(fn(Domains $d) => (string) $d->getId() === $currentDomainId);

        if (count($domains) < 1) {

            if ($this->principalDomainAdminAccess->isGranted($user, $this->getDomainData->getTenantCache())) {
                return;
            }
            throw new CustomUserMessageAccountStatusException('usuario no asociado a dominio');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {

    }
}
