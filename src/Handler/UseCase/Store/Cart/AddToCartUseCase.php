<?php

namespace App\Handler\UseCase\Store\Cart;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Others\ShoppingCart;
use App\Entity\Products\Products;
use App\Entity\Users\Users;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\AddToCartInterface;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Repository\Products\ProductsRepository;
use App\Service\Products\ProductStockInterface;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly ProductStockInterface $productStock,
        private readonly TranslatorInterface $translator,
    ) {}

    public function handler(string $productId, int $quantity = 1, ?string $colorId = null): array
    {
        try {
            if ($quantity < 1) {
                $quantity = 1;
            }

            $product = $this->findStoreProduct($productId);
            if ($product === null) {
                return ['success' => false, 'message' => $this->translator->trans('cart_product_not_found', [], 'store')];
            }

            $productColor = null;
            if (!empty($colorId)) {
                $productColor = $this->productsColorsRepository->find(StringUtil::convertToUuid($colorId));
                if ($productColor === null || !$productColor->isActive() || (string) $productColor->getProduct()->getId() !== (string) $product->getId()) {
                    return ['success' => false, 'message' => $this->translator->trans('cart_variant_not_available', [], 'store')];
                }
            }

            $user = $this->security->getUser();

            if ($user === null) {
                return $this->addToSession($product, $quantity, $productColor);
            }

            $existing = $this->cartRepository->findOneBy([
                'user'         => $user,
                'product'      => $product,
                'productColor' => $productColor,
                'active'       => true,
            ]);

            $currentQuantity = $existing?->getQuantity() ?? 0;
            $rejection = $this->rejectIfExceedsStock($product, $productColor, $currentQuantity, $quantity);
            if ($rejection !== null) {
                return $rejection;
            }

            if ($existing !== null) {
                $existing->updateQuantity($currentQuantity + $quantity);
                $this->em->add($existing, true);
            } else {
                $item = (new ShoppingCart())->add($user, $product, $quantity, $productColor);
                $this->em->add($item, true);
            }

            return ['success' => true, 'count' => $this->getDbCount($user)];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => $this->translator->trans('cart_add_error', [], 'store')];
        }
    }

    private function findStoreProduct(string $productId): ?Products
    {
        $product = $this->productsRepository->find(StringUtil::convertToUuid($productId));
        if ($product === null || !$product->isActive()) {
            return null;
        }

        $domain = $this->getDomainData->getDomainCache();
        if ((string) $product->getDomain()->getId() !== (string) $domain->getId()) {
            return null;
        }

        return $product;
    }

    private function rejectIfExceedsStock(Products $product, ?ProductsColors $productColor, int $currentQuantity, int $quantity): ?array
    {
        $available = $this->productStock->available($product, $productColor);
        if ($available === null || $currentQuantity + $quantity <= $available) {
            return null;
        }

        $message = $currentQuantity > 0
            ? $this->translator->trans('cart_quantity_exceeds_stock_with_cart', ['%max%' => $available, '%current%' => $currentQuantity, '%name%' => $product->getName()], 'store')
            : $this->translator->trans('cart_quantity_exceeds_stock', ['%max%' => $available, '%name%' => $product->getName()], 'store');

        return [
            'success'     => false,
            'message'     => $message,
            'maxQuantity' => $available,
            'inCart'      => $currentQuantity,
        ];
    }

    private function addToSession(Products $product, int $quantity, ?ProductsColors $productColor): array
    {
        $session  = $this->requestStack->getSession();
        $domainId = (string) $this->getDomainData->getDomainCache()->getId();
        $key      = "cart_{$domainId}";
        $cart     = $session->get($key, []);

        $productId = (string) $product->getId();
        $colorId   = $productColor !== null ? (string) $productColor->getId() : null;
        $itemKey   = $colorId === null ? $productId : "{$productId}:{$colorId}";

        $currentQuantity = (int) ($cart[$itemKey]['quantity'] ?? 0);
        $rejection = $this->rejectIfExceedsStock($product, $productColor, $currentQuantity, $quantity);
        if ($rejection !== null) {
            return $rejection;
        }

        $cart[$itemKey] = [
            'productId' => $productId,
            'colorId'   => $colorId,
            'quantity'  => $currentQuantity + $quantity,
            'addedAt'   => $cart[$itemKey]['addedAt'] ?? time(),
        ];

        $session->set($key, $cart);
        return ['success' => true, 'count' => array_sum(array_column($cart, 'quantity'))];
    }

    private function getDbCount(Users $user): int
    {
        return $this->cartRepository->getTotalQuantityForUser($user, $this->getDomainData->getDomainCache());
    }
}
