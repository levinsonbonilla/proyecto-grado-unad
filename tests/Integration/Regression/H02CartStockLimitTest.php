<?php

namespace App\Tests\Integration\Regression;

use App\Interface\UseCase\Store\Checkout\ConfirmOrderInterface;
use App\Tests\Integration\Support\StoreScenarioTestCase;

class H02CartStockLimitTest extends StoreScenarioTestCase
{
    public function testAddingExactlyTheAvailableStockIsAccepted(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $this->loginAs($customer);

        $result = $this->addToCart((string) $product->getId(), 13);

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $this->assertSame(13, $this->cartQuantity($customer, $product));
    }

    public function testAddingMoreThanTheAvailableStockIsRejected(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $this->loginAs($customer);

        $result = $this->addToCart((string) $product->getId(), 14);

        $this->assertFalse($result['success'] ?? true, 'El carrito no puede aceptar 14 unidades si solo hay 13');
        $this->assertSame(13, $result['maxQuantity'] ?? null);
        $this->assertStringContainsString('13', (string) ($result['message'] ?? ''));
        $this->assertNull($this->cartQuantity($customer, $product));
    }

    public function testAccumulatedCartQuantityCannotExceedStock(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $this->loginAs($customer);

        $this->assertTrue($this->addToCart((string) $product->getId(), 10)['success'] ?? false);
        $second = $this->addToCart((string) $product->getId(), 4);

        $this->assertFalse($second['success'] ?? true);
        $this->assertSame(10, $this->cartQuantity($customer, $product));
    }

    public function testUpdatingCartQuantityAboveStockIsRejected(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $this->loginAs($customer);
        $this->addToCart((string) $product->getId(), 1);

        $result = $this->updateCart($this->cartItemId($customer, $product), 14);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(13, $result['maxQuantity'] ?? null);
        $this->assertSame(1, $this->cartQuantity($customer, $product));

        $ok = $this->updateCart($this->cartItemId($customer, $product), 13);
        $this->assertTrue($ok['success'] ?? false);
        $this->assertSame(13, $this->cartQuantity($customer, $product));
    }

    public function testVariantBlockStockIsTheLimitWhenAVariantIsSelected(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $block = $this->createVariantBlock($product, 'Café', 4);
        $this->loginAs($customer);

        $result = $this->addToCart((string) $product->getId(), 5, (string) $block->getId());

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(4, $result['maxQuantity'] ?? null);
    }

    public function testGuestSessionCartIsAlsoLimited(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);

        $result = $this->addToCart((string) $product->getId(), 14);

        $this->assertFalse($result['success'] ?? true, 'El carrito de invitado tampoco puede superar el stock');
        $this->assertSame(13, $result['maxQuantity'] ?? null);
    }

    public function testProductPageLimitsQuantitySelectorToAvailableStock(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);

        $crawler = $this->client->request('GET', sprintf('%s/%s/product/%s', self::TENANT_A_HOST, self::LOCALE, $product->getId()));

        $this->assertResponseIsSuccessful();
        $input = $crawler->filter('.sec-product-detail input.num-product');
        $this->assertCount(1, $input);
        $this->assertSame('13', $input->attr('max'), 'El selector de cantidad debe tener como máximo el stock disponible');
        $this->assertStringContainsString('13', $crawler->filter('#product-stock-display')->text());
    }

    public function testCartPageLimitsQuantityInputToAvailableStock(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $this->loginAs($customer);
        $this->addToCart((string) $product->getId(), 2);

        $crawler = $this->client->request('GET', sprintf('%s/%s/cart', self::TENANT_A_HOST, self::LOCALE));

        $this->assertResponseIsSuccessful();
        $input = $crawler->filter(sprintf('tr[data-product-id="%s"] .cart-quantity-input', $product->getId()));
        $this->assertSame('13', $input->attr('max'));
    }

    public function testStockSoldByAnotherCustomerAfterAddingToCartBlocksCheckout(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $otherBuyer = $this->createUserForDomain('otro.comprador@proyecto-grado.test', $this->domain(), ['ROLE_USER']);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();

        $this->loginAs($customer);
        $this->assertTrue($this->addToCart($productId, 13)['success'] ?? false);

        $this->loginAs($otherBuyer);
        $this->assertTrue($this->addToCart($productId, 5)['success'] ?? false);
        $this->assertTrue($this->confirmOrder($this->paymentMethodId())['success'] ?? false);
        $this->assertSame(8, $this->freshProductStock($productId));

        $this->loginAs($customer);
        $result = $this->confirmOrder($this->paymentMethodId());

        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('Disponible: 8', (string) ($result['message'] ?? ''));
        $this->assertSame(8, $this->freshProductStock($productId));
        $this->assertSame(4, $this->countOrdersOf($customer), 'No se debe crear el pedido; el cliente solo conserva sus 4 pedidos de fixtures');
    }

    public function testCheckoutNeverOversellsWhenTwoBuyersRaceForTheLastUnits(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $otherBuyer = $this->createUserForDomain('otro.comprador@proyecto-grado.test', $this->domain(), ['ROLE_USER']);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();

        $this->loginAs($customer);
        $this->assertTrue($this->addToCart($productId, 13)['success'] ?? false);
        $this->loginAs($otherBuyer);
        $this->assertTrue($this->addToCart($productId, 13)['success'] ?? false);

        $secondary = $this->bootSecondaryContainer();
        $this->actAsIn($secondary, $otherBuyer);
        $secondary->get(\Doctrine\ORM\EntityManagerInterface::class)->getRepository(\App\Entity\Products\Products::class)->find($productId);

        $this->loginAs($customer);
        $first = $this->confirmOrder($this->paymentMethodId());
        $this->assertTrue($first['success'] ?? false, json_encode($first));
        $this->assertSame(0, $this->freshProductStock($productId));

        $second = $secondary->get(ConfirmOrderInterface::class)->handler($this->shippingData($this->paymentMethodId()));

        $this->assertFalse($second['success'] ?? true, 'El segundo comprador no puede llevarse unidades que ya no existen');
        $this->assertSame(0, $this->freshProductStock($productId));
        $this->assertSame(0, $this->countOrdersOf($otherBuyer));
    }
}
