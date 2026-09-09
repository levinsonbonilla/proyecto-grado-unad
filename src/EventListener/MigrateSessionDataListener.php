<?php

namespace App\EventListener;

use App\Entity\Products\Others\FavoriteProducts;
use App\Entity\Products\Others\ShoppingCart;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Repository\Products\Others\FavoriteProductsRepository;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Repository\Products\ProductsRepository;
use App\Util\StringUtil;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class MigrateSessionDataListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ShoppingCartRepository $cartRepository,
        private readonly FavoriteProductsRepository $favoriteRepository,
        private readonly ProductsRepository $productsRepository,
        private readonly CustomeEntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [SecurityEvents::INTERACTIVE_LOGIN => 'onLogin'];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user    = $event->getAuthenticationToken()->getUser();
        $session = $this->requestStack->getSession();

        try {
            $domainId = (string) $this->getDomainData->getDomainCache()->getId();
            $this->migrateCart($user, $session, $domainId);
            $this->migrateFavorites($user, $session, $domainId);
        } catch (\Throwable) {

        }
    }

    private function migrateCart(UserInterface $user, SessionInterface $session, string $domainId): void
    {
        $sessionCart = $session->get("cart_{$domainId}", []);
        if (empty($sessionCart)) {
            return;
        }

        foreach ($sessionCart as $productId => $entry) {
            $product = $this->productsRepository->find(StringUtil::convertToUuid((string) $productId));
            if ($product === null || !$product->isActive()) {
                continue;
            }

            $existing = $this->cartRepository->findOneBy([
                'user'    => $user,
                'product' => $product,
                'active'  => true,
            ]);

            $quantity = max(1, (int) ($entry['quantity'] ?? 1));

            if ($existing !== null) {
                $existing->updateQuantity($existing->getQuantity() + $quantity);
                $this->em->add($existing, false);
            } else {
                $this->em->add((new ShoppingCart())->add($user, $product, $quantity), false);
            }
        }

        $this->em->flush();
        $session->remove("cart_{$domainId}");
    }

    private function migrateFavorites(UserInterface $user, SessionInterface $session, string $domainId): void
    {
        $sessionFavorites = $session->get("favorites_{$domainId}", []);
        if (empty($sessionFavorites)) {
            return;
        }

        foreach ($sessionFavorites as $productId) {
            $product = $this->productsRepository->find(StringUtil::convertToUuid((string) $productId));
            if ($product === null || !$product->isActive()) {
                continue;
            }

            $existing = $this->favoriteRepository->findOneBy([
                'user'    => $user,
                'product' => $product,
            ]);

            if ($existing !== null) {
                if (!$existing->isActive()) {
                    $existing->activate();
                    $this->em->add($existing, false);
                }
            } else {
                $this->em->add((new FavoriteProducts())->add($user, $product), false);
            }
        }

        $this->em->flush();
        $session->remove("favorites_{$domainId}");
    }
}
