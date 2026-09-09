<?php

namespace App\Handler\UseCase\Store\Cart;

use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\UpdateCartQuantityInterface;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class UpdateCartQuantityUseCase implements UpdateCartQuantityInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ShoppingCartRepository $cartRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(string $cartItemId, int $quantity): array
    {
        try {
            if ($quantity < 1) {
                $quantity = 1;
            }

            $user = $this->security->getUser();

            if ($user === null) {
                return $this->updateSession($cartItemId, $quantity);
            }

            $item = $this->cartRepository->find(StringUtil::convertToUuid($cartItemId));
            if ($item === null || !$item->getUser()->getId()->equals($user->getId())) {
                return ['success' => false, 'message' => 'Ítem no encontrado.'];
            }

            $item->updateQuantity($quantity);
            $this->em->add($item, true);

            return ['success' => true, 'quantity' => $quantity];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al actualizar cantidad.'];
        }
    }

    private function updateSession(string $productId, int $quantity): array
    {
        $session  = $this->requestStack->getSession();
        $domainId = (string) $this->getDomainData->getDomainCache()->getId();
        $key      = "cart_{$domainId}";
        $cart     = $session->get($key, []);

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] = $quantity;
            $session->set($key, $cart);
        }

        return ['success' => true, 'quantity' => $quantity];
    }
}
