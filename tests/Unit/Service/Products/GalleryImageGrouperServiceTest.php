<?php

namespace App\Tests\Unit\Service\Products;

use App\Service\Products\GalleryImageGrouperService;
use PHPUnit\Framework\TestCase;

class GalleryImageGrouperServiceTest extends TestCase
{
    private GalleryImageGrouperService $service;

    protected function setUp(): void
    {
        $this->service = new GalleryImageGrouperService();
    }

    public function testCollapsesSamePhotoSharedByMultipleMedidasOfTheSameVariantGroup(): void
    {

        $images = [
            ['image' => 'foto.jpg', 'orderColumn' => 1, 'colorId' => 'row-s', 'variantGroupId' => 'group-1'],
            ['image' => 'foto.jpg', 'orderColumn' => 1, 'colorId' => 'row-m', 'variantGroupId' => 'group-1'],
            ['image' => 'foto.jpg', 'orderColumn' => 1, 'colorId' => 'row-l', 'variantGroupId' => 'group-1'],
        ];

        $result = $this->service->group($images);

        $this->assertCount(1, $result);
        $this->assertSame('foto.jpg', $result[0]['image']);
        $this->assertSame('row-s,row-m,row-l', $result[0]['colorIds']);
    }

    public function testKeepsDistinctPhotosOfTheSameGroupSeparate(): void
    {

        $images = [
            ['image' => 'a.jpg', 'orderColumn' => 1, 'colorId' => 'row-s', 'variantGroupId' => 'group-1'],
            ['image' => 'b.jpg', 'orderColumn' => 2, 'colorId' => 'row-s', 'variantGroupId' => 'group-1'],
            ['image' => 'a.jpg', 'orderColumn' => 1, 'colorId' => 'row-m', 'variantGroupId' => 'group-1'],
            ['image' => 'b.jpg', 'orderColumn' => 2, 'colorId' => 'row-m', 'variantGroupId' => 'group-1'],
            ['image' => 'a.jpg', 'orderColumn' => 1, 'colorId' => 'row-l', 'variantGroupId' => 'group-1'],
            ['image' => 'b.jpg', 'orderColumn' => 2, 'colorId' => 'row-l', 'variantGroupId' => 'group-1'],
        ];

        $result = $this->service->group($images);

        $this->assertCount(2, $result);
        $this->assertSame('a.jpg', $result[0]['image']);
        $this->assertSame('row-s,row-m,row-l', $result[0]['colorIds']);
        $this->assertSame('b.jpg', $result[1]['image']);
        $this->assertSame('row-s,row-m,row-l', $result[1]['colorIds']);
    }

    public function testNeverMergesRowsWithoutAVariantGroupEvenWithTheSameUrl(): void
    {

        $images = [
            ['image' => 'coincidencia.jpg', 'orderColumn' => 1, 'colorId' => 'row-negro', 'variantGroupId' => null],
            ['image' => 'coincidencia.jpg', 'orderColumn' => 1, 'colorId' => 'row-blanco', 'variantGroupId' => null],
        ];

        $result = $this->service->group($images);

        $this->assertCount(2, $result);
        $this->assertSame('row-negro', $result[0]['colorIds']);
        $this->assertSame('row-blanco', $result[1]['colorIds']);
    }

    public function testKeepsBehaviorUnchangedForBlocksWithoutMedidaOrColor(): void
    {

        $images = [
            ['image' => 'x.jpg', 'orderColumn' => 1, 'colorId' => null, 'variantGroupId' => null],
            ['image' => 'y.jpg', 'orderColumn' => 2, 'colorId' => 'row-1', 'variantGroupId' => null],
        ];

        $result = $this->service->group($images);

        $this->assertCount(2, $result);
        $this->assertSame('', $result[0]['colorIds']);
        $this->assertSame('row-1', $result[1]['colorIds']);
    }

    public function testPreservesFirstAppearanceOrder(): void
    {
        $images = [
            ['image' => 'b.jpg', 'orderColumn' => 2, 'colorId' => 'row-1', 'variantGroupId' => null],
            ['image' => 'a.jpg', 'orderColumn' => 1, 'colorId' => 'row-2', 'variantGroupId' => null],
        ];

        $result = $this->service->group($images);

        $this->assertSame('b.jpg', $result[0]['image']);
        $this->assertSame('a.jpg', $result[1]['image']);
    }

    public function testReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], $this->service->group([]));
    }
}
