<?php

namespace App\Handler\UseCase\Store\Cart;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\UpdateCartQuantityInterface;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Repository\Products\ProductsRepository;
use App\Service\Products\ProductStockInterface;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UpdateCartQuantityUseCase implements UpdateCartQuantityInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ShoppingCartRepository $cartRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
        private readonly ProductsRepository $productsRepository,
        private readonly ProductsColorsRepository $productsColorsRepository,
        private readonly ProductStockInterface $productStock,
        private readonly TranslatorInterface $translator,
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
                return ['success' => false, 'message' => $this->translator->trans('cart_item_not_found', [], 'store')];
            }

            $rejection = $this->rejectIfExceedsStock($item->getProduct(), $item->getProductColor(), $quantity, $item->getQuantity());
            if ($rejection !== null) {
                return $rejection;
            }

            $item->updateQuantity($quantity);
            $this->em->add($item, true);

            return ['success' => true, 'quantity' => $quantity];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => $this->translator->trans('cart_update_error', [], 'store')];
        }
    }

    private function rejectIfExceedsStock(Products $product, ?ProductsColors $productColor, int $quantity, int $previousQuantity): ?array
    {
        $available = $this->productStock->available($product, $productColor);
        if ($available === null || $quantity <= $available) {
            return null;
        }

        return [
            'success'     => false,
            'message'     => $this->translator->trans('cart_quantity_exceeds_stock', ['%max%' => $available, '%name%' => $product->getName()], 'store'),
            'maxQuantity' => $available,
            'quantity'    => $previousQuantity,
        ];
    }

    private function updateSession(string $itemKey, int $quantity): array
    {
        $session  = $this->requestStack->getSession();
        $domainId = (string) $this->getDomainData->getDomainCache()->getId();
        $key      = "cart_{$domainId}";
        $cart     = $session->get($key, []);

        if (!isset($cart[$itemKey])) {
            return ['success' => false, 'message' => $this->translator->trans('cart_item_not_found', [], 'store')];
        }

        $product = $this->productsRepository->find(StringUtil::convertToUuid((string) $cart[$itemKey]['productId']));
        if ($product === null) {
            return ['success' => false, 'message' => $this->translator->trans('cart_item_not_found', [], 'store')];
        }
        $productColor = empty($cart[$itemKey]['colorId'])
            ? null
            : $this->productsColorsRepository->find(StringUtil::convertToUuid((string) $cart[$itemKey]['colorId']));

        $rejection = $this->rejectIfExceedsStock($product, $productColor, $quantity, (int) $cart[$itemKey]['quantity']);
        if ($rejection !== null) {
            return $rejection;
        }

        $cart[$itemKey]['quantity'] = $quantity;
        $session->set($key, $cart);

        return ['success' => true, 'quantity' => $quantity];
    }
}
