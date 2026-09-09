<?php

namespace App\Tests\Unit\Service\Products;

use App\Exception\GenericException;
use App\Service\Products\ProductVariantStockValidatorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductVariantStockValidatorServiceTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;
    private ProductVariantStockValidatorService $service;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturn('Excede el stock disponible');

        $this->service = new ProductVariantStockValidatorService($this->translator);
    }

    private function block(?string $stock, array $overrides = []): array
    {
        return array_merge([
            'stock' => $stock,
            'images' => [['id' => '', 'currentImage' => 'http://localhost/foto.jpg', 'position' => '1']],
        ], $overrides);
    }

    public function testValidateDoesNothingWhenGeneralStockIsNull(): void
    {
        $this->service->validate(null, [$this->block('999'), $this->block('999')]);

        $this->addToAssertionCount(1);
    }

    public function testValidateDoesNothingWhenSumEqualsGeneralStock(): void
    {
        $this->service->validate(10, [$this->block('6'), $this->block('4')]);

        $this->addToAssertionCount(1);
    }

    public function testValidateDoesNothingWhenSumIsBelowGeneralStock(): void
    {
        $this->service->validate(10, [$this->block('6'), $this->block('3')]);

        $this->addToAssertionCount(1);
    }

    public function testValidateThrowsWhenSumExceedsGeneralStock(): void
    {
        $this->expectException(GenericException::class);

        $this->service->validate(10, [$this->block('6'), $this->block('5')]);
    }

    public function testValidateIgnoresBlocksWithoutImages(): void
    {
        $this->service->validate(10, [
            ['stock' => '999', 'images' => []],
            ['stock' => '999'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testValidateIgnoresBlocksWithEmptyStock(): void
    {
        $this->service->validate(10, [
            $this->block('6'),
            $this->block(''),
            $this->block(null),
        ]);

        $this->addToAssertionCount(1);
    }

    public function testValidateCountsBlockStockOnceRegardlessOfPhotoCount(): void
    {
        $this->service->validate(15, [
            $this->block('10', ['images' => [
                ['id' => '', 'currentImage' => 'http://localhost/a.jpg', 'position' => '1'],
                ['id' => '', 'currentImage' => 'http://localhost/b.jpg', 'position' => '2'],
                ['id' => '', 'currentImage' => 'http://localhost/c.jpg', 'position' => '3'],
            ]]),
        ]);

        $this->addToAssertionCount(1);
    }

    public function testValidateThrowsWithAssignedAndGeneralInMessage(): void
    {
        $this->translator->expects($this->once())->method('trans')
            ->with('product_color_stock_exceeds_general', [
                '%assigned%' => 11,
                '%general%' => 10,
            ], 'modules')
            ->willReturn('Excede el stock disponible');

        $this->expectException(GenericException::class);
        $this->expectExceptionMessage('Excede el stock disponible');

        $this->service->validate(10, [$this->block('6'), $this->block('5')]);
    }
}
