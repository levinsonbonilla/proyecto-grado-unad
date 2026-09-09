<?php

namespace App\Handler\UseCase\Store\Cart;

use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\RemoveFromCartInterface;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class RemoveFromCartUseCase implements RemoveFromCartInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ShoppingCartRepository $cartRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(string $cartItemId): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return $this->removeFromSession($cartItemId);
            }

            $item = $this->cartRepository->find(StringUtil::convertToUuid($cartItemId));
            if ($item === null || !$item->getUser()->getId()->equals($user->getId())) {
                return ['success' => false, 'message' => 'Ítem no encontrado.'];
            }

            $item->deactivate();
            $this->em->add($item, true);

            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al eliminar del carrito.'];
        }
    }

    private function removeFromSession(string $productId): array
    {
        $session  = $this->requestStack->getSession();
        $domainId = (string) $this->getDomainData->getDomainCache()->getId();
        $key      = "cart_{$domainId}";
        $cart     = $session->get($key, []);

        unset($cart[$productId]);
        $session->set($key, $cart);

        return ['success' => true];
    }
}
