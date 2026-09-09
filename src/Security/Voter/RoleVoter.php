<?php

namespace App\Security\Voter;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\PrincipalDomainAdminAccessInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class RoleVoter extends Voter
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly PrincipalDomainAdminAccessInterface $principalDomainAdminAccess,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {

        return in_array($attribute, ['ROLE_ADMIN', 'ROLE_USER', 'ROLE_SUPER_ADMIN']);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        $domain = $this->getDomainData->getDomainCache();
        $userRoles = $user->getUsersDomainsActivesByDomain($domain);

        if ($userRoles && in_array($attribute, $userRoles->getRoles())) {
            return true;
        }

        if ($attribute === 'ROLE_ADMIN') {
            return $this->principalDomainAdminAccess->isGranted($user, $this->getDomainData->getTenantCache());
        }

        return false;
    }
}
