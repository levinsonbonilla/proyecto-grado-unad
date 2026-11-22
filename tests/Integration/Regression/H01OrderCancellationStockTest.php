<?php

namespace App\Tests\Integration\Regression;

use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\PaymentTransactions;
use App\Interface\UseCase\Modules\Sales\Orders\UpdateSaleStatusInterface;
use App\Interface\UseCase\Store\Orders\CancelOrderInterface;
use App\Tests\Integration\Support\StoreScenarioTestCase;
use Doctrine\ORM\EntityManagerInterface;

class H01OrderCancellationStockTest extends StoreScenarioTestCase
{
    private function placeOrder(string $productId, int $quantity, ?string $colorId = null): string
    {
        $this->loginAs($this->user(self::CUSTOMER_EMAIL));
        $added = $this->addToCart($productId, $quantity, $colorId);
        $this->assertTrue($added['success'] ?? false, json_encode($added));

        $result = $this->confirmOrder($this->paymentMethodId());
        $this->assertTrue($result['success'] ?? false, json_encode($result));

        return $result['orderId'];
    }

    public function testCancellingPendingOrderRestoresStock(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();

        $orderId = $this->placeOrder($productId, 1);
        $this->assertSame(12, $this->freshProductStock($productId), 'El checkout debe descontar 1 unidad');

        $this->cancelAsCustomer($orderId);

        $this->assertSame('Cancelado', $this->freshOrderStatus($orderId));
        $this->assertSame(13, $this->freshProductStock($productId), 'Cancelar debe devolver la unidad al inventario');
    }

    public function testCancellingRestoresVariantBlockStock(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $block = $this->createVariantBlock($product, 'Café', 4);

        $orderId = $this->placeOrder((string) $product->getId(), 2, (string) $block->getId());
        $this->assertSame(2, $this->freshBlockStock((string) $block->getId()));
        $this->assertSame(11, $this->freshProductStock((string) $product->getId()));

        $this->cancelAsCustomer($orderId);

        $this->assertSame(4, $this->freshBlockStock((string) $block->getId()));
        $this->assertSame(13, $this->freshProductStock((string) $product->getId()));
    }

    public function testRepeatedCancellationRestoresStockOnlyOnce(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();
        $orderId = $this->placeOrder($productId, 1);

        $this->cancelAsCustomer($orderId);
        $this->cancelAsCustomer($orderId);
        $this->cancelAsCustomer($orderId);

        $this->assertSame('Cancelado', $this->freshOrderStatus($orderId));
        $this->assertSame(13, $this->freshProductStock($productId), 'Repetir la cancelación no puede devolver stock de más');
    }

    public function testConcurrentCancellationsRestoreStockOnlyOnce(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();
        $orderId = $this->placeOrder($productId, 1);
        $customer = $this->user(self::CUSTOMER_EMAIL);

        $secondary = $this->bootSecondaryContainer();
        $this->actAsIn($secondary, $customer, self::TENANT_A_HOST, '/es/orders/' . $orderId . '/cancel');
        $staleOrder = $secondary->get(EntityManagerInterface::class)->getRepository(Orders::class)->find($orderId);
        $this->assertSame('Pendiente', $staleOrder->getStatus()->getName());

        $this->cancelAsCustomer($orderId);
        $this->assertSame(13, $this->freshProductStock($productId));

        $secondary->get(CancelOrderInterface::class)->handler($staleOrder);

        $this->assertSame('Cancelado', $this->freshOrderStatus($orderId));
        $this->assertSame(13, $this->freshProductStock($productId), 'Una segunda cancelación simultánea no puede duplicar la devolución');
    }

    public function testCustomerAndAdminCancellingAtTheSameTimeRestoreStockOnlyOnce(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();
        $orderId = $this->placeOrder($productId, 3);
        $this->assertSame(10, $this->freshProductStock($productId));

        $secondary = $this->bootSecondaryContainer();
        $this->actAsIn($secondary, $this->user(self::ADMIN_EMAIL), self::TENANT_A_HOST, '/es/dashboard/sales/orders/' . $orderId . '/status');
        $staleOrder = $secondary->get(EntityManagerInterface::class)->getRepository(Orders::class)->find($orderId);

        $this->cancelAsCustomer($orderId);
        $secondary->get(UpdateSaleStatusInterface::class)->handler($staleOrder, 'Cancelado');

        $this->assertSame(13, $this->freshProductStock($productId));
    }

    public function testAdminCancellationRestoresStock(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();
        $orderId = $this->placeOrder($productId, 2);

        $this->loginAs($this->user(self::ADMIN_EMAIL));
        $result = $this->postJson(
            sprintf('%s/%s/dashboard/sales/orders/%s/status', self::TENANT_A_HOST, self::LOCALE, $orderId),
            ['status' => 'Cancelado']
        );

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $this->assertSame(13, $this->freshProductStock($productId));
    }

    public function testFailedPaymentWebhookRestoresStock(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();
        $orderId = $this->placeOrder($productId, 1);

        $this->em->clear();
        $tx = $this->em->getRepository(PaymentTransactions::class)->findOneBy(['orders' => $orderId]);
        $this->assertNotNull($tx);
        $tx->setGatewayData('pi_regresion_h01', null);
        $this->em->flush();

        $body = json_encode(['intentId' => 'pi_regresion_h01', 'status' => 'failed']);
        $secret = (string) ($_SERVER['PAYMENT_GATEWAY_SECRET'] ?? $_ENV['PAYMENT_GATEWAY_SECRET'] ?? '');
        $this->client->request('POST', self::TENANT_A_HOST . '/checkout/webhook/payment', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GATEWAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ], $body);
        $this->assertResponseIsSuccessful();

        $this->assertSame('Cancelado', $this->freshOrderStatus($orderId));
        $this->assertSame(13, $this->freshProductStock($productId));

        $this->client->request('POST', self::TENANT_A_HOST . '/checkout/webhook/payment', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GATEWAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ], $body);
        $this->assertSame(13, $this->freshProductStock($productId), 'Un webhook repetido no puede devolver stock de nuevo');
    }

    public function testCancellingNonCancellableOrderDoesNotTouchStock(): void
    {
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $productId = (string) $product->getId();
        $orderId = $this->placeOrder($productId, 1);

        $this->loginAs($this->user(self::ADMIN_EMAIL));
        $this->postJson(sprintf('%s/%s/dashboard/sales/orders/%s/status', self::TENANT_A_HOST, self::LOCALE, $orderId), ['status' => 'Procesando']);
        $this->postJson(sprintf('%s/%s/dashboard/sales/orders/%s/status', self::TENANT_A_HOST, self::LOCALE, $orderId), ['status' => 'Enviado']);

        $this->loginAs($this->user(self::CUSTOMER_EMAIL));
        $this->cancelAsCustomer($orderId);

        $this->assertSame('Enviado', $this->freshOrderStatus($orderId));
        $this->assertSame(12, $this->freshProductStock($productId));
    }
}
