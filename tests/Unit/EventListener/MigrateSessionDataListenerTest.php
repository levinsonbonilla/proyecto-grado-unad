<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Products\Others\FavoriteProducts;
use App\Entity\Products\Others\ShoppingCart;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\EventListener\MigrateSessionDataListener;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Repository\Products\Others\FavoriteProductsRepository;
use App\Repository\Products\Others\ShoppingCartRepository;
use App\Repository\Products\ProductsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Uid\Uuid;

class MigrateSessionDataListenerTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private SessionInterface&MockObject $session;
    private GetDomainDataInterface&MockObject $getDomainData;
    private ShoppingCartRepository&MockObject $cartRepository;
    private FavoriteProductsRepository&MockObject $favoriteRepository;
    private ProductsRepository&MockObject $productsRepository;
    private CustomeEntityManagerInterface&MockObject $em;
    private MigrateSessionDataListener $listener;

    private const DOMAIN_KEY  = '11111111-1111-1111-1111-111111111111';
    private const PRODUCT_ID  = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        $this->requestStack       = $this->createMock(RequestStack::class);
        $this->session            = $this->createMock(SessionInterface::class);
        $this->getDomainData      = $this->createMock(GetDomainDataInterface::class);
        $this->cartRepository     = $this->createMock(ShoppingCartRepository::class);
        $this->favoriteRepository = $this->createMock(FavoriteProductsRepository::class);
        $this->productsRepository = $this->createMock(ProductsRepository::class);
        $this->em                 = $this->createMock(CustomeEntityManagerInterface::class);

        $this->requestStack->method('getSession')->willReturn($this->session);

        $domain = $this->createMock(Domains::class);
        $domain->method('getId')->willReturn(Uuid::fromString(self::DOMAIN_KEY));
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->listener = new MigrateSessionDataListener(
            $this->requestStack,
            $this->getDomainData,
            $this->cartRepository,
            $this->favoriteRepository,
            $this->productsRepository,
            $this->em,
        );
    }

    private function makeEvent(): InteractiveLoginEvent
    {
        $user  = $this->createMock(Users::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return new InteractiveLoginEvent(new Request(), $token);
    }

    private function activeProduct(): Products&MockObject
    {
        $product = $this->createMock(Products::class);
        $product->method('isActive')->willReturn(true);
        return $product;
    }

    public function testOnLoginMigratesNewCartItemToDatabase(): void
    {
        $this->session->method('get')->willReturnMap([
            ["cart_" . self::DOMAIN_KEY, [], [self::PRODUCT_ID => ['quantity' => 2]]],
            ["favorites_" . self::DOMAIN_KEY, [], []],
        ]);

        $this->productsRepository->method('find')->willReturn($this->activeProduct());
        $this->cartRepository->method('findOneBy')->willReturn(null);

        $this->em->expects($this->once())->method('add')
            ->with($this->isInstanceOf(ShoppingCart::class), false);
        $this->em->expects($this->once())->method('flush');
        $this->session->expects($this->once())->method('remove')->with('cart_' . self::DOMAIN_KEY);

        $this->listener->onLogin($this->makeEvent());
    }

    public function testOnLoginSumsQuantityIntoExistingCartItem(): void
    {
        $this->session->method('get')->willReturnMap([
            ["cart_" . self::DOMAIN_KEY, [], [self::PRODUCT_ID => ['quantity' => 2]]],
            ["favorites_" . self::DOMAIN_KEY, [], []],
        ]);

        $this->productsRepository->method('find')->willReturn($this->activeProduct());

        $existing = $this->createMock(ShoppingCart::class);
        $existing->method('getQuantity')->willReturn(3);
        $existing->expects($this->once())->method('updateQuantity')->with(5);
        $this->cartRepository->method('findOneBy')->willReturn($existing);

        $this->em->expects($this->once())->method('add')->with($existing, false);

        $this->listener->onLogin($this->makeEvent());
    }

    public function testOnLoginMigratesNewFavoriteToDatabase(): void
    {
        $this->session->method('get')->willReturnMap([
            ["cart_" . self::DOMAIN_KEY, [], []],
            ["favorites_" . self::DOMAIN_KEY, [], [self::PRODUCT_ID]],
        ]);

        $this->productsRepository->method('find')->willReturn($this->activeProduct());
        $this->favoriteRepository->method('findOneBy')->willReturn(null);

        $this->em->expects($this->once())->method('add')
            ->with($this->isInstanceOf(FavoriteProducts::class), false);
        $this->em->expects($this->once())->method('flush');
        $this->session->expects($this->once())->method('remove')->with('favorites_' . self::DOMAIN_KEY);

        $this->listener->onLogin($this->makeEvent());
    }

    public function testOnLoginReactivatesExistingInactiveFavorite(): void
    {
        $this->session->method('get')->willReturnMap([
            ["cart_" . self::DOMAIN_KEY, [], []],
            ["favorites_" . self::DOMAIN_KEY, [], [self::PRODUCT_ID]],
        ]);

        $this->productsRepository->method('find')->willReturn($this->activeProduct());

        $existing = $this->createMock(FavoriteProducts::class);
        $existing->method('isActive')->willReturn(false);
        $existing->expects($this->once())->method('activate');
        $this->favoriteRepository->method('findOneBy')->willReturn($existing);

        $this->em->expects($this->once())->method('add')->with($existing, false);

        $this->listener->onLogin($this->makeEvent());
    }

    public function testOnLoginDoesNotTouchExistingActiveFavorite(): void
    {
        $this->session->method('get')->willReturnMap([
            ["cart_" . self::DOMAIN_KEY, [], []],
            ["favorites_" . self::DOMAIN_KEY, [], [self::PRODUCT_ID]],
        ]);

        $this->productsRepository->method('find')->willReturn($this->activeProduct());

        $existing = $this->createMock(FavoriteProducts::class);
        $existing->method('isActive')->willReturn(true);
        $existing->expects($this->never())->method('activate');
        $this->favoriteRepository->method('findOneBy')->willReturn($existing);

        $this->em->expects($this->never())->method('add');

        $this->em->expects($this->once())->method('flush');
        $this->session->expects($this->once())->method('remove')->with('favorites_' . self::DOMAIN_KEY);

        $this->listener->onLogin($this->makeEvent());
    }

    public function testOnLoginSkipsInactiveOrMissingProducts(): void
    {
        $this->session->method('get')->willReturnMap([
            ["cart_" . self::DOMAIN_KEY, [], ['missing-id' => ['quantity' => 1]]],
            ["favorites_" . self::DOMAIN_KEY, [], ['inactive-id']],
        ]);

        $inactiveProduct = $this->createMock(Products::class);
        $inactiveProduct->method('isActive')->willReturn(false);

        $this->productsRepository->method('find')->willReturnOnConsecutiveCalls(null, $inactiveProduct);

        $this->em->expects($this->never())->method('add');

        $this->listener->onLogin($this->makeEvent());
    }

    public function testOnLoginDoesNothingWhenSessionEmpty(): void
    {
        $this->session->method('get')->willReturn([]);

        $this->em->expects($this->never())->method('add');
        $this->em->expects($this->never())->method('flush');
        $this->session->expects($this->never())->method('remove');

        $this->listener->onLogin($this->makeEvent());
    }

    public function testOnLoginSilentlyCatchesExceptions(): void
    {
        $this->getDomainData->method('getDomainCache')
            ->willThrowException(new \RuntimeException('No domain'));

        $this->listener->onLogin($this->makeEvent());
        $this->assertTrue(true);
    }

    public function testGetSubscribedEventsReturnsInteractiveLogin(): void
    {
        $events = MigrateSessionDataListener::getSubscribedEvents();
        $this->assertArrayHasKey('security.interactive_login', $events);
    }
}
