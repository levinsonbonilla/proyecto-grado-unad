<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Favorites;

use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Favorites\GetFavoritesUseCase;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Others\FavoriteProductsRepository;
use App\Repository\Products\ProductsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Uid\Uuid;

class GetFavoritesUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private GetDomainDataInterface&MockObject $getDomainData;
    private FavoriteProductsRepository&MockObject $favoriteProductsRepository;
    private ProductsRepository&MockObject $productsRepository;
    private RequestStack&MockObject $requestStack;
    private SessionInterface&MockObject $session;
    private LogInterface&MockObject $log;
    private GetFavoritesUseCase $useCase;

    protected function setUp(): void
    {
        $this->security                   = $this->createMock(Security::class);
        $this->getDomainData              = $this->createMock(GetDomainDataInterface::class);
        $this->favoriteProductsRepository = $this->createMock(FavoriteProductsRepository::class);
        $this->productsRepository         = $this->createMock(ProductsRepository::class);
        $this->requestStack               = $this->createMock(RequestStack::class);
        $this->session                    = $this->createMock(SessionInterface::class);
        $this->log                        = $this->createMock(LogInterface::class);

        $this->requestStack->method('getSession')->willReturn($this->session);

        $domain = $this->createMock(Domains::class);
        $domain->method('getId')->willReturn(Uuid::fromString('11111111-1111-1111-1111-111111111111'));
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->useCase = new GetFavoritesUseCase(
            $this->security,
            $this->getDomainData,
            $this->favoriteProductsRepository,
            $this->productsRepository,
            $this->requestStack,
            $this->log,
        );
    }

    public function testHandlerReturnsDbFavoritesForLoggedUser(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);

        $fakeFavorites = [
            ['id' => 'abc', 'name' => 'Producto Test A', 'publicPrice' => '100000', 'image' => null, 'favoriteId' => 'fav-1'],
        ];
        $this->favoriteProductsRepository->expects($this->once())
            ->method('getActiveForUser')
            ->with($user)
            ->willReturn($fakeFavorites);

        $result = $this->useCase->handler();

        $this->assertSame($fakeFavorites, $result);
    }

    public function testHandlerReturnsSessionFavoritesForGuest(): void
    {
        $productId = '550e8400-e29b-41d4-a716-446655440000';

        $this->security->method('getUser')->willReturn(null);
        $this->session->method('get')
            ->with('favorites_11111111-1111-1111-1111-111111111111', [])
            ->willReturn([$productId]);

        $product = $this->createMock(Products::class);
        $product->method('isActive')->willReturn(true);
        $product->method('getId')->willReturn(Uuid::fromString($productId));
        $product->method('getName')->willReturn('Producto Test A');
        $product->method('getPublicPrice')->willReturn('100000');

        $this->productsRepository->method('find')->willReturn($product);

        $result = $this->useCase->handler();

        $this->assertCount(1, $result);
        $this->assertSame($productId, $result[0]['id']);
        $this->assertSame('Producto Test A', $result[0]['name']);
        $this->assertSame('100000', $result[0]['publicPrice']);
        $this->assertNull($result[0]['image']);
    }

    public function testHandlerSkipsInactiveProductsForGuest(): void
    {
        $productId = '550e8400-e29b-41d4-a716-446655440000';

        $this->security->method('getUser')->willReturn(null);
        $this->session->method('get')->willReturn([$productId]);

        $product = $this->createMock(Products::class);
        $product->method('isActive')->willReturn(false);
        $this->productsRepository->method('find')->willReturn($product);

        $result = $this->useCase->handler();

        $this->assertSame([], $result);
    }

    public function testHandlerSkipsMissingProductsForGuest(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->session->method('get')->willReturn(['non-existent-id']);
        $this->productsRepository->method('find')->willReturn(null);

        $result = $this->useCase->handler();

        $this->assertSame([], $result);
    }

    public function testHandlerReturnsEmptyArrayWhenGuestSessionEmpty(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->session->method('get')->willReturn([]);

        $this->productsRepository->expects($this->never())->method('find');

        $result = $this->useCase->handler();

        $this->assertSame([], $result);
    }

    public function testHandlerReturnsEmptyArrayOnExceptionForLoggedUser(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->favoriteProductsRepository->method('getActiveForUser')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();

        $this->assertSame([], $result);
    }

    public function testHandlerReturnsEmptyArrayOnExceptionForGuest(): void
    {

        $failingDomainData = $this->createMock(GetDomainDataInterface::class);
        $failingDomainData->method('getDomainCache')
            ->willThrowException(new \RuntimeException('No domain'));

        $useCase = new GetFavoritesUseCase(
            $this->security,
            $failingDomainData,
            $this->favoriteProductsRepository,
            $this->productsRepository,
            $this->requestStack,
            $this->log,
        );

        $this->security->method('getUser')->willReturn(null);
        $this->log->expects($this->once())->method('handler');

        $result = $useCase->handler();

        $this->assertSame([], $result);
    }
}
