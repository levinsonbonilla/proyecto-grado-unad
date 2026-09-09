<?php

namespace App\Handler\UseCase\Store\Favorites;

use App\Entity\Products\Others\FavoriteProducts;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Favorites\ToggleFavoriteInterface;
use App\Repository\Products\Others\FavoriteProductsRepository;
use App\Repository\Products\ProductsRepository;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class ToggleFavoriteUseCase implements ToggleFavoriteInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly FavoriteProductsRepository $favoriteRepository,
        private readonly ProductsRepository $productsRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(string $productId): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return $this->toggleSession($productId);
            }

            $product = $this->productsRepository->find(StringUtil::convertToUuid($productId));
            if ($product === null) {
                return ['success' => false, 'message' => 'Producto no encontrado.'];
            }

            $existing = $this->favoriteRepository->findOneBy([
                'user'    => $user,
                'product' => $product,
            ]);

            if ($existing !== null) {
                $existing->isActive() ? $existing->deactivate() : $existing->activate();
                $this->em->add($existing, true);
                $isFavorite = $existing->isActive();
            } else {
                $favorite = (new FavoriteProducts())->add($user, $product);
                $this->em->add($favorite, true);
                $isFavorite = true;
            }

            return ['success' => true, 'isFavorite' => $isFavorite];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al actualizar favoritos.'];
        }
    }

    private function toggleSession(string $productId): array
    {
        $session   = $this->requestStack->getSession();
        $domainId  = (string) $this->getDomainData->getDomainCache()->getId();
        $key       = "favorites_{$domainId}";
        $favorites = $session->get($key, []);

        if (in_array($productId, $favorites, true)) {
            $favorites  = array_values(array_diff($favorites, [$productId]));
            $isFavorite = false;
        } else {
            $favorites[]= $productId;
            $isFavorite = true;
        }

        $session->set($key, $favorites);
        return ['success' => true, 'isFavorite' => $isFavorite];
    }
}
