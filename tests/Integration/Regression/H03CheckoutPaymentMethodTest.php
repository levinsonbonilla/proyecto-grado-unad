<?php

namespace App\Tests\Integration\Regression;

use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Products\Orders\OrdersRepository;
use App\Tests\Integration\Support\StoreScenarioTestCase;

class H03CheckoutPaymentMethodTest extends StoreScenarioTestCase
{
    private function prepareCart(int $quantity = 1): array
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $this->loginAs($customer);
        $this->assertTrue($this->addToCart((string) $product->getId(), $quantity)['success'] ?? false);

        return [$customer, $product];
    }

    public function testCheckoutWithoutPaymentMethodIsRejected(): void
    {
        [$customer, $product] = $this->prepareCart();

        $result = $this->confirmOrder(null);

        $this->assertFalse($result['success'] ?? true, 'No se puede registrar un pedido sin método de pago');
        $this->assertSame(4, $this->countOrdersOf($customer));
        $this->assertSame(13, $this->freshProductStock((string) $product->getId()));
    }

    public function testCheckoutWithUnknownPaymentMethodIsRejected(): void
    {
        [$customer] = $this->prepareCart();

        $result = $this->confirmOrder('00000000-0000-4000-8000-000000000000');

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(4, $this->countOrdersOf($customer));
    }

    public function testCheckoutWithInactivePaymentMethodIsRejected(): void
    {
        [$customer] = $this->prepareCart();
        $method = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Efectivo', 'domain' => $this->domain()]);
        $method->deactivate();
        $this->em->flush();

        $result = $this->confirmOrder((string) $method->getId());

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(4, $this->countOrdersOf($customer));
    }

    public function testCheckoutWithPaymentMethodOfAnotherTenantIsRejected(): void
    {
        [$customer] = $this->prepareCart();
        $foreign = $this->createPaymentMethod($this->domain(self::TENANT_B_HOST), 'Efectivo tenant B');

        $result = $this->confirmOrder((string) $foreign->getId());

        $this->assertFalse($result['success'] ?? true, 'Un método de pago de otro negocio no es válido aquí');
        $this->assertSame(4, $this->countOrdersOf($customer));
    }

    public function testCheckoutWhenTheStoreHasNoPaymentMethodsShowsClearMessage(): void
    {
        foreach ($this->em->getRepository(PaymentMethods::class)->findBy(['domain' => $this->domain()]) as $method) {
            $method->deactivate();
        }
        $this->em->flush();
        $this->prepareCart();

        $crawler = $this->client->request('GET', sprintf('%s/%s/checkout', self::TENANT_A_HOST, self::LOCALE));

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#noPaymentMethodsNotice'));
        $this->assertNotNull($crawler->filter('#confirmOrderBtn')->attr('disabled'));
    }

    public function testCheckoutWithAvailablePaymentMethodSucceeds(): void
    {
        [$customer] = $this->prepareCart();

        $result = $this->confirmOrder($this->paymentMethodId());

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $this->assertSame(5, $this->countOrdersOf($customer));
    }

    public function testSalesKpiSeparatesConfirmedSalesFromPendingPayments(): void
    {
        $this->prepareCart(2);
        $this->assertTrue($this->confirmOrder($this->paymentMethodId())['success'] ?? false);

        $kpi = static::getContainer()->get(OrdersRepository::class)->getSalesKpiSummary(
            $this->domain(),
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('+1 day'),
        );

        $this->assertArrayHasKey('montoPendientePago', $kpi);
        $this->assertSame(200000 * 1 + 600000, $kpi['montoPendientePago'], 'Pendiente de pago = pedido Pendiente de fixtures (200000) + nuevo pedido (600000)');
        $this->assertSame(400000, $kpi['totalVentas'], 'Total ventas solo suma pedidos con pago confirmado (Procesando + Enviado de fixtures)');
    }
}
