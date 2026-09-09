<?php

namespace App\Interface\Configuration;

use App\Entity\Tenants\Tenants;
use Symfony\Component\Security\Core\User\UserInterface;

interface PrincipalDomainAdminAccessInterface
{
    public function isGranted(UserInterface $user, Tenants $tenant): bool;
}
