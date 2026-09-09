<?php

namespace App\Handler\UseCase\Store\Cart;

use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\ClearCartInterface;
use App\Repository\Products\Others\ShoppingCartRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class ClearCartUseCase implements ClearCartInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ShoppingCartRepository $cartRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(): void
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                $session  = $this->requestStack->getSession();
                $domainId = (string) $this->getDomainData->getDomainCache()->getId();
                $session->remove("cart_{$domainId}");
                return;
            }

            $items = $this->cartRepository->findBy(['user' => $user, 'active' => true]);
            foreach ($items as $item) {
                $item->deactivate();
                $this->em->add($item, false);
            }
            $this->em->flush();
        } catch (\Throwable $th) {
            $this->log->handler($th);
        }
    }
}
