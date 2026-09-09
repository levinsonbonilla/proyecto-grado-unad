<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Favorites;

use App\Entity\Products\Others\FavoriteProducts;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Favorites\ToggleFavoriteUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
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

class ToggleFavoriteUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private GetDomainDataInterface&MockObject $getDomainData;
    private FavoriteProductsRepository&MockObject $favoriteRepository;
    private ProductsRepository&MockObject $productsRepository;
    private CustomeEntityManagerInterface&MockObject $em;
    private RequestStack&MockObject $requestStack;
    private SessionInterface&MockObject $session;
    private LogInterface&MockObject $log;
    private ToggleFavoriteUseCase $useCase;

    private const PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        $this->security           = $this->createMock(Security::class);
        $this->getDomainData      = $this->createMock(GetDomainDataInterface::class);
        $this->favoriteRepository = $this->createMock(FavoriteProductsRepository::class);
        $this->productsRepository = $this->createMock(ProductsRepository::class);
        $this->em                 = $this->createMock(CustomeEntityManagerInterface::class);
        $this->requestStack       = $this->createMock(RequestStack::class);
        $this->session            = $this->createMock(SessionInterface::class);
        $this->log                = $this->createMock(LogInterface::class);

        $this->requestStack->method('getSession')->willReturn($this->session);

        $domain = $this->createMock(Domains::class);
        $domain->method('getId')->willReturn(Uuid::fromString('11111111-1111-1111-1111-111111111111'));
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->useCase = new ToggleFavoriteUseCase(
            $this->security,
            $this->getDomainData,
            $this->favoriteRepository,
            $this->productsRepository,
            $this->em,
            $this->requestStack,
            $this->log,
        );
    }

    public function testHandlerCreatesNewFavoriteForLoggedUser(): void
    {
        $user    = $this->createMock(Users::class);
        $product = $this->createMock(Products::class);

        $this->security->method('getUser')->willReturn($user);
        $this->productsRepository->method('find')->willReturn($product);
        $this->favoriteRepository->method('findOneBy')->willReturn(null);

        $this->em->expects($this->once())->method('add')
            ->with($this->isInstanceOf(FavoriteProducts::class), true);

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isFavorite']);
    }

    public function testHandlerDeactivatesExistingActiveFavoriteForLoggedUser(): void
    {
        $user     = $this->createMock(Users::class);
        $product  = $this->createMock(Products::class);
        $existing = $this->createMock(FavoriteProducts::class);

        $this->security->method('getUser')->willReturn($user);
        $this->productsRepository->method('find')->willReturn($product);
        $this->favoriteRepository->method('findOneBy')->willReturn($existing);

        $existing->method('isActive')->willReturnOnConsecutiveCalls(true, false);
        $existing->expects($this->once())->method('deactivate');
        $existing->expects($this->never())->method('activate');

        $this->em->expects($this->once())->method('add')->with($existing, true);

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['isFavorite']);
    }

    public function testHandlerActivatesExistingInactiveFavoriteForLoggedUser(): void
    {
        $user     = $this->createMock(Users::class);
        $product  = $this->createMock(Products::class);
        $existing = $this->createMock(FavoriteProducts::class);

        $this->security->method('getUser')->willReturn($user);
        $this->productsRepository->method('find')->willReturn($product);
        $this->favoriteRepository->method('findOneBy')->willReturn($existing);

        $existing->method('isActive')->willReturnOnConsecutiveCalls(false, true);
        $existing->expects($this->once())->method('activate');
        $existing->expects($this->never())->method('deactivate');

        $this->em->expects($this->once())->method('add')->with($existing, true);

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isFavorite']);
    }

    public function testHandlerReturnsErrorWhenProductNotFoundForLoggedUser(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->productsRepository->method('find')->willReturn(null);

        $this->em->expects($this->never())->method('add');

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('Producto no encontrado.', $result['message']);
    }

    public function testHandlerReturnsGenericErrorOnExceptionForLoggedUser(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->productsRepository->method('find')->willThrowException(new \RuntimeException('DB error'));

        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('Error al actualizar favoritos.', $result['message']);
    }

    public function testHandlerAddsToSessionForGuestWhenNotAlreadyFavorite(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->session->method('get')
            ->with('favorites_11111111-1111-1111-1111-111111111111', [])
            ->willReturn([]);

        $this->session->expects($this->once())->method('set')
            ->with('favorites_11111111-1111-1111-1111-111111111111', [self::PRODUCT_ID]);

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isFavorite']);
    }

    public function testHandlerRemovesFromSessionForGuestWhenAlreadyFavorite(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->session->method('get')
            ->with('favorites_11111111-1111-1111-1111-111111111111', [])
            ->willReturn([self::PRODUCT_ID]);

        $this->session->expects($this->once())->method('set')
            ->with('favorites_11111111-1111-1111-1111-111111111111', []);

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['isFavorite']);
    }

    public function testHandlerKeepsOtherProductsInSessionWhenTogglingOneOff(): void
    {
        $otherProductId = '660e8400-e29b-41d4-a716-446655440001';

        $this->security->method('getUser')->willReturn(null);
        $this->session->method('get')
            ->willReturn([self::PRODUCT_ID, $otherProductId]);

        $this->session->expects($this->once())->method('set')
            ->with('favorites_11111111-1111-1111-1111-111111111111', [$otherProductId]);

        $result = $this->useCase->handler(self::PRODUCT_ID);

        $this->assertFalse($result['isFavorite']);
    }
}
