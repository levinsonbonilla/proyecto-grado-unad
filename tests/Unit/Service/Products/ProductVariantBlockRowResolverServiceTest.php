<?php

namespace App\Tests\Unit\Service\Products;

use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Service\Products\ColorResolverInterface;
use App\Service\Products\MedidaResolverInterface;
use App\Service\Products\ProductVariantBlockRowResolverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductVariantBlockRowResolverServiceTest extends TestCase
{
    private ColorResolverInterface&MockObject $colorResolver;
    private MedidaResolverInterface&MockObject $medidaResolver;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private TranslatorInterface&MockObject $translator;
    private Products&MockObject $product;
    private Domains&MockObject $domain;
    private ProductVariantBlockRowResolverService $service;

    protected function setUp(): void
    {
        $this->colorResolver = $this->createMock(ColorResolverInterface::class);
        $this->medidaResolver = $this->createMock(MedidaResolverInterface::class);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
        $this->product = $this->createMock(Products::class);
        $this->domain = $this->createMock(Domains::class);

        $this->service = new ProductVariantBlockRowResolverService(
            $this->colorResolver,
            $this->medidaResolver,
            $this->entityManager,
            $this->translator,
        );
    }

    public function testResolveReturnsEmptyArrayWhenBlockHasNoColorMedidaOrStock(): void
    {
        $rows = $this->service->resolve(['colorName' => '', 'stock' => ''], null, $this->product, $this->domain);

        $this->assertSame([], $rows);
    }

    public function testResolveReturnsOneRowForBlockWithColorOnly(): void
    {
        $color = $this->createMock(Colors::class);
        $this->colorResolver->method('resolve')->willReturn($color);

        $rows = $this->service->resolve(['colorName' => 'Negro', 'stock' => ''], null, $this->product, $this->domain);

        $this->assertCount(1, $rows);
        $this->assertInstanceOf(ProductsColors::class, $rows[0]);
        $this->assertSame($color, $rows[0]->getColor());
        $this->assertNull($rows[0]->getMedida());
    }

    public function testResolveReturnsOneRowForBlockWithStockOnly(): void
    {
        $rows = $this->service->resolve(['stock' => '7'], null, $this->product, $this->domain);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->getColor());
        $this->assertSame(7, $rows[0]->getStock());
    }

    public function testResolveEditsExistingRowInPlaceWhenRowIdMatches(): void
    {
        $existingRow = $this->createMock(ProductsColors::class);
        $existingRow->expects($this->once())->method('edit')->willReturnSelf();
        $existingRow->expects($this->once())->method('activate');

        $rows = $this->service->resolve(
            ['rowId' => 'row-1', 'stock' => '5'],
            null,
            $this->product,
            $this->domain,
            ['row-1' => $existingRow],
        );

        $this->assertSame([$existingRow], $rows);
    }

    public function testResolveCreatesOneRowPerMedidaEntrySharingSameColor(): void
    {
        $color = $this->createMock(Colors::class);
        $this->colorResolver->method('resolve')->willReturn($color);
        $s = $this->createMock(Medidas::class);
        $m = $this->createMock(Medidas::class);
        $l = $this->createMock(Medidas::class);
        $this->medidaResolver->method('resolve')->willReturnOnConsecutiveCalls($s, $m, $l);

        $rows = $this->service->resolve([
            'colorName' => 'Rosa',
            'medidas' => [
                ['medidaName' => 'S', 'stock' => '4'],
                ['medidaName' => 'M', 'stock' => '6'],
                ['medidaName' => 'L', 'stock' => '2'],
            ],
        ], null, $this->product, $this->domain);

        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame($color, $row->getColor());
        }
        $this->assertSame($s, $rows[0]->getMedida());
        $this->assertSame($m, $rows[1]->getMedida());
        $this->assertSame($l, $rows[2]->getMedida());
        $this->assertSame(4, $rows[0]->getStock());
        $this->assertSame(6, $rows[1]->getStock());
        $this->assertSame(2, $rows[2]->getStock());
    }

    public function testResolveGivesAllNewRowsTheSameVariantGroupId(): void
    {
        $this->medidaResolver->method('resolve')->willReturnCallback(
            fn () => $this->createMock(Medidas::class)
        );

        $rows = $this->service->resolve([
            'medidas' => [
                ['medidaName' => 'S', 'stock' => '4'],
                ['medidaName' => 'M', 'stock' => '6'],
            ],
        ], null, $this->product, $this->domain);

        $this->assertCount(2, $rows);
        $this->assertNotNull($rows[0]->getVariantGroupId());
        $this->assertSame((string) $rows[0]->getVariantGroupId(), (string) $rows[1]->getVariantGroupId());
    }

    public function testResolveSkipsEmptyMedidaEntry(): void
    {
        $this->medidaResolver->expects($this->once())->method('resolve')->willReturn($this->createMock(Medidas::class));

        $rows = $this->service->resolve([
            'medidas' => [
                ['medidaName' => '', 'stock' => '1'],
                ['medidaName' => 'M', 'stock' => '6'],
            ],
        ], null, $this->product, $this->domain);

        $this->assertCount(1, $rows);
    }

    public function testResolveThrowsGenericExceptionOnDuplicateMedidaNameInSameBlock(): void
    {
        $this->medidaResolver->method('resolve')->willReturnCallback(
            fn () => $this->createMock(Medidas::class)
        );

        $this->expectException(\App\Exception\GenericException::class);

        $this->service->resolve([
            'medidas' => [
                ['medidaName' => 'S', 'stock' => '4'],
                ['medidaName' => 'M', 'stock' => '6'],
                ['medidaName' => 'S', 'stock' => '2'],
            ],
        ], null, $this->product, $this->domain);
    }

    public function testResolveThrowsGenericExceptionOnDuplicateMedidaNameCaseInsensitiveAndTrimmed(): void
    {
        $this->medidaResolver->method('resolve')->willReturnCallback(
            fn () => $this->createMock(Medidas::class)
        );

        $this->expectException(\App\Exception\GenericException::class);

        $this->service->resolve([
            'medidas' => [
                ['medidaName' => ' s ', 'stock' => '4'],
                ['medidaName' => 'S', 'stock' => '6'],
            ],
        ], null, $this->product, $this->domain);
    }

    public function testResolveReusesVariantGroupIdFromExistingRowMatchedByAnyEntry(): void
    {
        $existingGroupId = '11111111-1111-1111-1111-111111111111';
        $existingRow = $this->createMock(ProductsColors::class);
        $existingRow->method('getVariantGroupId')->willReturn(Uuid::fromString($existingGroupId));
        $existingRow->method('edit')->willReturnSelf();

        $this->medidaResolver->method('resolve')->willReturnCallback(
            fn () => $this->createMock(Medidas::class)
        );

        $rows = $this->service->resolve([
            'medidas' => [

                ['rowId' => 'row-existing', 'medidaName' => 'S', 'stock' => '4'],

                ['medidaName' => 'M', 'stock' => '6'],
            ],
        ], null, $this->product, $this->domain, ['row-existing' => $existingRow]);

        $this->assertCount(2, $rows);
        $this->assertSame($existingGroupId, (string) $rows[0]->getVariantGroupId());

        $this->assertSame($existingGroupId, (string) $rows[1]->getVariantGroupId());
    }

    public function testResolveOnlyCallsColorResolverOnceEvenWithMultipleMedidas(): void
    {
        $this->colorResolver->expects($this->once())->method('resolve')->willReturn($this->createMock(Colors::class));
        $this->medidaResolver->method('resolve')->willReturnCallback(
            fn () => $this->createMock(Medidas::class)
        );

        $this->service->resolve([
            'colorName' => 'Rosa',
            'medidas' => [
                ['medidaName' => 'S', 'stock' => '4'],
                ['medidaName' => 'M', 'stock' => '6'],
            ],
        ], null, $this->product, $this->domain);
    }

    public function testResolveAppliesBlockOrderToTheSingleResolvedRow(): void
    {
        $rows = $this->service->resolve(['stock' => '7', 'order' => '3'], null, $this->product, $this->domain);

        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]->getOrderColumn());
    }

    public function testResolveLeavesOrderColumnNullWhenBlockOrderIsEmpty(): void
    {
        $rows = $this->service->resolve(['stock' => '7'], null, $this->product, $this->domain);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->getOrderColumn());
    }

    public function testResolveAppliesSameBlockOrderToAllRowsWithMultipleMedidas(): void
    {
        $this->medidaResolver->method('resolve')->willReturnCallback(
            fn () => $this->createMock(Medidas::class)
        );

        $rows = $this->service->resolve([
            'order' => '5',
            'medidas' => [
                ['medidaName' => 'S', 'stock' => '4'],
                ['medidaName' => 'M', 'stock' => '6'],
            ],
        ], null, $this->product, $this->domain);

        $this->assertCount(2, $rows);
        $this->assertSame(5, $rows[0]->getOrderColumn());
        $this->assertSame(5, $rows[1]->getOrderColumn());
    }
}
