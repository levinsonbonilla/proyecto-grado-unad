<?php

namespace App\Tests\Integration\Service\Products;

use App\Exception\GenericException;
use App\Service\Products\ProductVariantStockValidatorInterface;
use App\Service\Products\ProductVariantStockValidatorService;
use App\Tests\Integration\IntegrationTestCase;

class ProductVariantStockValidatorServiceIntegrationTest extends IntegrationTestCase
{
    private function block(?string $stock): array
    {
        return [
            'stock' => $stock,
            'images' => [['id' => '', 'currentImage' => 'http://localhost/foto.jpg', 'position' => '1']],
        ];
    }

    public function testServiceIsRegisteredInContainerViaItsInterface(): void
    {
        $this->assertInstanceOf(
            ProductVariantStockValidatorService::class,
            static::getContainer()->get(ProductVariantStockValidatorInterface::class),
        );
    }

    public function testValidateThrowsRealTranslatedMessageWhenSumExceedsGeneralStock(): void
    {
        $validator = static::getContainer()->get(ProductVariantStockValidatorInterface::class);

        try {
            $validator->validate(10, [$this->block('6'), $this->block('5')]);
            $this->fail('Se esperaba GenericException');
        } catch (GenericException $e) {

            $this->assertStringNotContainsString('product_color_stock_exceeds_general', $e->getMessage());
            $this->assertStringNotContainsString('%assigned%', $e->getMessage());
            $this->assertStringContainsString('11', $e->getMessage());
            $this->assertStringContainsString('10', $e->getMessage());
        }
    }

    public function testValidateDoesNotThrowWhenGeneralStockIsNull(): void
    {
        $validator = static::getContainer()->get(ProductVariantStockValidatorInterface::class);

        $validator->validate(null, [$this->block('999')]);

        $this->addToAssertionCount(1);
    }
}
