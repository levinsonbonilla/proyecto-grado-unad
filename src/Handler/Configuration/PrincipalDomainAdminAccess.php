<?php

namespace App\Handler\Configuration;

use App\Entity\Tenants\Tenants;
use App\Entity\Users\UsersDomains;
use App\Interface\Configuration\PrincipalDomainAdminAccessInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class PrincipalDomainAdminAccess implements PrincipalDomainAdminAccessInterface
{
    public function isGranted(UserInterface $user, Tenants $tenant): bool
    {
        if (!$tenant->isPrincipal()) {
            return false;
        }

        foreach ($user->getUserDomainsActives() as $usersDomains) {

            if (in_array('ROLE_ADMIN', $usersDomains->getRoles() ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
