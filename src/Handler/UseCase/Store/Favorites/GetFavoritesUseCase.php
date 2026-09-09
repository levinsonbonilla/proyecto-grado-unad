<?php

namespace App\Handler\UseCase\Store\Favorites;

use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Favorites\GetFavoritesInterface;
use App\Repository\Products\Others\FavoriteProductsRepository;
use App\Repository\Products\ProductsRepository;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class GetFavoritesUseCase implements GetFavoritesInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly FavoriteProductsRepository $favoriteProductsRepository,
        private readonly ProductsRepository $productsRepository,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(): array
    {
        try {
            $user  = $this->security->getUser();
            $items = $user instanceof Users
                ? $this->favoriteProductsRepository->getActiveForUser($user)
                : $this->getSessionFavorites();

            return $items;
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return [];
        }
    }

    private function getSessionFavorites(): array
    {
        $session    = $this->requestStack->getSession();
        $domainId   = (string) $this->getDomainData->getDomainCache()->getId();
        $productIds = $session->get("favorites_{$domainId}", []);

        if (empty($productIds)) {
            return [];
        }

        $items = [];
        foreach ($productIds as $productId) {
            $product = $this->productsRepository->find(StringUtil::convertToUuid((string) $productId));
            if ($product === null || !$product->isActive()) {
                continue;
            }
            $items[] = [
                'id'          => (string) $product->getId(),
                'name'        => $product->getName(),
                'publicPrice' => $product->getPublicPrice(),
                'stock'       => $product->getStock(),
                'image'       => null,
            ];
        }

        return $items;
    }
}
