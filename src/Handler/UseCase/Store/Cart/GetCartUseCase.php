<?php

namespace App\Handler\UseCase\Store\Cart;

use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\GetCartInterface;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Repository\Products\ProductsRepository;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class GetCartUseCase implements GetCartInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ShoppingCartRepository $cartRepository,
        private readonly ProductsRepository $productsRepository,
        private readonly ProductsColorsRepository $productsColorsRepository,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(): array
    {
        try {
            $user = $this->security->getUser();
            return $user === null
                ? $this->getSessionCart()
                : $this->getDbCart($user);
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return $this->emptyCart();
        }
    }

    private function getDbCart(Users $user): array
    {
        $items = $this->cartRepository->getActiveItemsForUser($user);

        foreach ($items as &$item) {

            if (!empty($item['colorImage'])) {
                $item['image'] = $item['colorImage'];
            }
            $item['colorId'] = $item['productColorId'] ?? null;
        }
        unset($item);

        return $this->buildCartResponse($items);
    }

    private function getSessionCart(): array
    {
        $session  = $this->requestStack->getSession();
        $domainId = (string) $this->getDomainData->getDomainCache()->getId();
        $sessionCart = $session->get("cart_{$domainId}", []);

        if (empty($sessionCart)) {
            return $this->emptyCart();
        }

        $items = [];
        foreach ($sessionCart as $itemKey => $entry) {

            $productId = (string) ($entry['productId'] ?? $itemKey);
            $colorId   = $entry['colorId'] ?? null;

            $product = $this->productsRepository->find(StringUtil::convertToUuid($productId));
            if ($product === null || !$product->isActive()) {
                continue;
            }

            $colorName  = null;
            $colorHex   = null;
            $colorImage = null;
            $medidaName = null;
            if (!empty($colorId)) {
                $productColor = $this->productsColorsRepository->find(StringUtil::convertToUuid($colorId));
                if ($productColor !== null && $productColor->isActive()) {

                    $colorName  = $productColor->getColor()?->getName();
                    $colorHex   = $productColor->getColor()?->getHexCode();
                    $colorImage = $productColor->getImage();
                    $medidaName = $productColor->getMedida()?->getName();
                }
            }

            $items[] = [
                'cartItemId'    => $itemKey,
                'productId'     => (string) $product->getId(),
                'name'          => $product->getName(),
                'publicPrice'   => $product->getPublicPrice(),
                'image'         => $colorImage,
                'quantity'      => $entry['quantity'],
                'addedAt'       => $entry['addedAt'],
                'colorId'       => $colorId,
                'colorName'     => $colorName,
                'colorHexCode'  => $colorHex,
                'medidaName'    => $medidaName,
            ];
        }

        return $this->buildCartResponse($items);
    }

    private function buildCartResponse(array $items): array
    {
        $subtotal = 0;
        foreach ($items as &$item) {
            $itemTotal = (int) $item['publicPrice'] * (int) $item['quantity'];
            $item['itemTotal'] = $itemTotal;
            $subtotal += $itemTotal;
        }
        unset($item);

        return [
            'items'    => $items,
            'subtotal' => $subtotal,
            'count'    => array_sum(array_column($items, 'quantity')),
        ];
    }

    private function emptyCart(): array
    {
        return ['items' => [], 'subtotal' => 0, 'count' => 0];
    }
}
