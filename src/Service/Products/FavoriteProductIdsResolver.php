<?php

namespace App\Service\Products;

use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Repository\Products\Others\FavoriteProductsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class FavoriteProductIdsResolver implements FavoriteProductIdsResolverInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly FavoriteProductsRepository $favoriteProductsRepository,
        private readonly RequestStack $requestStack,
    ) {}

    public function resolve(): array
    {
        $user = $this->security->getUser();
        if ($user instanceof Users) {
            return $this->favoriteProductsRepository->getActiveProductIdsForUser($user);
        }

        $session  = $this->requestStack->getSession();
        $domainId = (string) $this->getDomainData->getDomainCache()->getId();

        return $session->get("favorites_{$domainId}", []);
    }
}
