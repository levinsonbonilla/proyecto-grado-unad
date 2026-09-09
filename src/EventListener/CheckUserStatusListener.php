<?php

namespace App\EventListener;

use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\PrincipalDomainAdminAccessInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Core\User\UserInterface;

class CheckUserStatusListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly EntityManagerInterface $entityManager,
        private readonly PrincipalDomainAdminAccessInterface $principalDomainAdminAccess,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        if (!$user->isActive()) {
            throw new DisabledException();
        }

        if (!$user->isValidatedEmail()) {
            throw new CustomUserMessageAccountStatusException('Debe validar su perfil.');
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
}
