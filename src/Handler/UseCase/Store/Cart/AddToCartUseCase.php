<?php

namespace App\Handler\UseCase\Store\Cart;

use App\Entity\Products\Others\ShoppingCart;
use App\Entity\Users\Users;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\AddToCartInterface;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Repository\Products\ProductsRepository;
use App\Util\StringUtil;
use App\Util\UUIDUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class AddToCartUseCase implements AddToCartInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ShoppingCartRepository $cartRepository,
        private readonly ProductsRepository $productsRepository,
        private readonly ProductsColorsRepository $productsColorsRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(string $productId, int $quantity = 1, ?string $colorId = null): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return $this->addToSession($productId, $quantity, $colorId);
            }

            $product = $this->productsRepository->find(StringUtil::convertToUuid($productId));
            if ($product === null || !$product->isActive()) {
                return ['success' => false, 'message' => 'Producto no encontrado.'];
            }

            $productColor = null;
            if (!empty($colorId)) {
                $productColor = $this->productsColorsRepository->find(StringUtil::convertToUuid($colorId));
                if ($productColor === null || !$productColor->isActive() || (string) $productColor->getProduct()->getId() !== (string) $product->getId()) {
                    return ['success' => false, 'message' => 'Color no disponible para este producto.'];
                }
            }

            $existing = $this->cartRepository->findOneBy([
                'user'         => $user,
                'product'      => $product,
                'productColor' => $productColor,
                'active'       => true,
            ]);

            if ($existing !== null) {
                $existing->updateQuantity($existing->getQuantity() + $quantity);
                $this->em->add($existing, true);
            } else {
                $item = (new ShoppingCart())->add($user, $product, $quantity, $productColor);
                $this->em->add($item, true);
            }

            return ['success' => true, 'count' => $this->getDbCount($user)];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al agregar al carrito.'];
        }
    }

    private function addToSession(string $productId, int $quantity, ?string $colorId = null): array
    {
        $session  = $this->requestStack->getSession();
        $domainId = (string) $this->getDomainData->getDomainCache()->getId();
        $key      = "cart_{$domainId}";
        $cart     = $session->get($key, []);

        $itemKey = empty($colorId) ? $productId : "{$productId}:{$colorId}";

        $cart[$itemKey] = [
            'productId' => $productId,
            'colorId'   => $colorId,
            'quantity'  => ($cart[$itemKey]['quantity'] ?? 0) + $quantity,
            'addedAt'   => $cart[$itemKey]['addedAt'] ?? time(),
        ];

        $session->set($key, $cart);
        return ['success' => true, 'count' => array_sum(array_column($cart, 'quantity'))];
    }

    private function getDbCount(Users $user): int
    {
        return $this->cartRepository->getTotalQuantityForUser($user);
    }
}
