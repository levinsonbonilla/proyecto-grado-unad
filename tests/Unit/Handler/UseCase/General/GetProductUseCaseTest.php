<?php

namespace App\Tests\Unit\Handler\UseCase\General;

use App\Entity\Products\Products;
use App\Handler\UseCase\General\GetProductUseCase;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\ProductsRepository;
use App\Service\Products\FavoriteProductIdsResolverInterface;
use App\Service\Products\GalleryImageGrouperService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class GetProductUseCaseTest extends TestCase
{
    private ProductsRepository&MockObject $productsRepository;
    private FavoriteProductIdsResolverInterface&MockObject $favoriteProductIds;
    private ProductsColorsRepository&MockObject $productsColorsRepository;
    private Products&MockObject $product;
    private GetProductUseCase $useCase;

    protected function setUp(): void
    {
        $this->productsRepository = $this->createMock(ProductsRepository::class);
        $this->favoriteProductIds = $this->createMock(FavoriteProductIdsResolverInterface::class);
        $this->productsColorsRepository = $this->createMock(ProductsColorsRepository::class);

        $this->favoriteProductIds->method('resolve')->willReturn([]);
        $this->productsRepository->method('getProductToDetailInECommerce')->willReturn([]);

        $this->product = $this->createMock(Products::class);
        $this->product->method('getId')->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'));
        $this->product->method('getName')->willReturn('Mouse');
        $this->product->method('getDescription')->willReturn('desc');
        $this->product->method('getPublicPrice')->willReturn('10000');

        $this->useCase = new GetProductUseCase(
            $this->productsRepository,
            $this->favoriteProductIds,
            $this->productsColorsRepository,

            new GalleryImageGrouperService(),
        );
    }

    public function testHandlerIncludesColorsFromRepository(): void
    {
        $colors = [
            ['id' => 'color-1', 'stock' => 5, 'image' => null, 'colorId' => 'color-1', 'colorName' => 'Negro', 'hexCode' => '#000000'],
        ];
        $this->productsColorsRepository->expects($this->once())
            ->method('getActiveByProduct')
            ->with($this->product)
            ->willReturn($colors);

        $result = $this->useCase->handler($this->product);

        $this->assertSame($colors, $result['colors']);
    }

    public function testHandlerReturnsEmptyColorsWhenProductHasNone(): void
    {
        $this->productsColorsRepository->method('getActiveByProduct')->willReturn([]);

        $result = $this->useCase->handler($this->product);

        $this->assertSame([], $result['colors']);
    }
}
