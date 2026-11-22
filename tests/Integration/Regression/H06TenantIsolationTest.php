<?php

namespace App\Tests\Integration\Regression;

use App\Entity\Products\Categories\Categories;
use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Entity\Users\Users;
use App\Repository\Products\Orders\OrdersRepository;
use App\Tests\Integration\Support\StoreScenarioTestCase;

class H06TenantIsolationTest extends StoreScenarioTestCase
{
    private const DENIED = [403, 404];
    private const PANEL_HOST = self::TENANT_A_HOST;

    private Users $tenantBAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantBAdmin = $this->createUserForDomain('admin.tenant-b@proyecto-grado.test', $this->domain(self::TENANT_B_HOST), ['ROLE_ADMIN']);
    }

    private function tenantAOrder(string $status = 'Pendiente'): Orders
    {
        $orders = $this->em->getRepository(Orders::class)->findAll();
        foreach ($orders as $order) {
            if ($order->getStatus()->getName() === $status) {
                return $order;
            }
        }
        $this->fail("No hay pedido {$status} del tenant A en fixtures");
    }

    private function tenantAEntity(string $class): object
    {
        $entity = $this->em->getRepository($class)->findOneBy(['domain' => $this->domain()]);
        $this->assertNotNull($entity, "Debe existir un {$class} del tenant A");
        return $entity;
    }

    private function asTenantB(string $method, string $path, array $json = null): int
    {
        $this->loginAs($this->tenantBAdmin);
        $server = $json === null ? [] : ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        $this->client->request($method, self::PANEL_HOST . '/' . self::LOCALE . $path, [], [], $server, $json === null ? null : json_encode($json));
        return $this->client->getResponse()->getStatusCode();
    }

    private function assertDenied(int $status, string $what): void
    {
        $this->assertContains($status, self::DENIED, "{$what}: el admin del tenant B obtuvo HTTP {$status} sobre un recurso del tenant A");
    }

    public function testTenantBAdminCannotViewTenantAOrder(): void
    {
        $order = $this->tenantAOrder('Enviado');
        $this->assertDenied($this->asTenantB('GET', '/dashboard/sales/orders/' . $order->getId()), 'Detalle de pedido');
    }

    public function testTenantBAdminCannotChangeTenantAOrderStatus(): void
    {
        $order = $this->tenantAOrder('Pendiente');
        $orderId = (string) $order->getId();

        $status = $this->asTenantB('POST', '/dashboard/sales/orders/' . $orderId . '/status', ['status' => 'Cancelado']);

        $this->assertDenied($status, 'Cambio de estado de pedido');
        $this->assertSame('Pendiente', $this->freshOrderStatus($orderId));
    }

    public function testTenantBAdminCannotEditOrToggleTenantAProduct(): void
    {
        $product = $this->tenantAEntity(Products::class);
        $productId = (string) $product->getId();

        $this->assertDenied($this->asTenantB('GET', '/dashboard/products/' . $productId . '/edit'), 'Edición de producto');
        $this->assertDenied($this->asTenantB('POST', '/dashboard/products/' . $productId . '/toggle-status'), 'Activar/desactivar producto');

        $this->em->clear();
        $this->assertTrue($this->em->getRepository(Products::class)->find($productId)->isActive());
    }

    public function testTenantBAdminCannotTouchTenantACatalogConfiguration(): void
    {
        $category = $this->tenantAEntity(Categories::class);
        $color = $this->tenantAEntity(Colors::class);
        $medida = $this->tenantAEntity(Medidas::class);
        $method = $this->tenantAEntity(PaymentMethods::class);

        $this->assertDenied($this->asTenantB('GET', '/dashboard/products/categories/' . $category->getId() . '/edit'), 'Edición de categoría');
        $this->assertDenied($this->asTenantB('POST', '/dashboard/products/categories/' . $category->getId() . '/toggle-status'), 'Toggle de categoría');
        $this->assertDenied($this->asTenantB('GET', '/dashboard/products/colors/' . $color->getId() . '/edit'), 'Edición de color');
        $this->assertDenied($this->asTenantB('GET', '/dashboard/products/medidas/' . $medida->getId() . '/edit'), 'Edición de medida');
        $this->assertDenied($this->asTenantB('GET', '/dashboard/products/payment-methods/' . $method->getId() . '/edit'), 'Edición de método de pago');
        $this->assertDenied($this->asTenantB('POST', '/dashboard/products/payment-methods/' . $method->getId() . '/delete'), 'Borrado de método de pago');

        $this->em->clear();
        $this->assertTrue($this->em->getRepository(PaymentMethods::class)->find($method->getId())->isActive());
        $this->assertTrue($this->em->getRepository(Categories::class)->find($category->getId())->isActive());
    }

    public function testTenantBAdminCannotViewOrEditTenantAUsers(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $customerId = (string) $customer->getId();

        $this->assertDenied($this->asTenantB('GET', '/dashboard/users/' . $customerId . '/show'), 'Ver usuario');
        $this->assertDenied($this->asTenantB('GET', '/dashboard/users/' . $customerId . '/edit'), 'Editar usuario');
        $this->assertDenied($this->asTenantB('POST', '/dashboard/users/' . $customerId . '/toggle-status'), 'Activar/desactivar usuario');

        $this->em->clear();
        $this->assertTrue($this->em->getRepository(Users::class)->find($customerId)->isActive());
    }

    public function testTenantBAdminCannotEditTenantADomain(): void
    {
        $domainA = $this->domain();
        $this->assertDenied($this->asTenantB('GET', '/dashboard/configurations/domains/' . $domainA->getId() . '/edit'), 'Editar dominio');
        $this->assertDenied($this->asTenantB('POST', '/dashboard/configurations/domains/' . $domainA->getId() . '/toggle-status'), 'Toggle de dominio');
    }

    public function testTenantBOrdersListDoesNotLeakTenantAOrders(): void
    {
        $this->asTenantB('GET', '/dashboard/sales/orders/list');
        $this->assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();

        foreach ($this->em->getRepository(Orders::class)->findAll() as $order) {
            $this->assertStringNotContainsString((string) $order->getId(), $body);
        }
    }

    public function testTenantBAdminCanStillManageItsOwnProducts(): void
    {
        $own = $this->createTrackedProduct($this->domain(self::TENANT_B_HOST), 'Producto propio tenant B', 5);

        $status = $this->asTenantB('GET', '/dashboard/products/' . $own->getId() . '/edit');

        $this->assertSame(200, $status, 'El aislamiento no debe bloquear los recursos propios del tenant B');
    }

    public function testTenantAAdminCannotReachTenantBProduct(): void
    {
        $foreign = $this->createTrackedProduct($this->domain(self::TENANT_B_HOST), 'Producto tenant B', 5);
        $this->loginAs($this->user(self::ADMIN_EMAIL));

        $this->client->request('GET', self::PANEL_HOST . '/es/dashboard/products/' . $foreign->getId() . '/edit');

        $this->assertContains($this->client->getResponse()->getStatusCode(), self::DENIED);
    }

    public function testOwnTenantAccessKeepsWorking(): void
    {
        $this->loginAs($this->user(self::ADMIN_EMAIL));
        $order = $this->tenantAOrder('Enviado');
        $product = $this->tenantAEntity(Products::class);

        $this->client->request('GET', self::TENANT_A_HOST . '/es/dashboard/sales/orders/' . $order->getId());
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', self::TENANT_A_HOST . '/es/dashboard/products/' . $product->getId() . '/edit');
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', self::TENANT_A_HOST . '/es/dashboard/users/' . $this->user(self::CUSTOMER_EMAIL)->getId() . '/show');
        $this->assertResponseIsSuccessful();
    }

    public function testStorefrontOfTenantBDoesNotServeTenantAProducts(): void
    {
        $product = $this->tenantAEntity(Products::class);

        $this->client->request('GET', self::TENANT_B_HOST . '/es/product/' . $product->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCustomerCannotAddTenantAProductToTenantBCart(): void
    {
        $product = $this->tenantAEntity(Products::class);
        $this->loginAs($this->user(self::CUSTOMER_EMAIL));

        $result = $this->addToCart((string) $product->getId(), 1, null, self::TENANT_B_HOST);

        $this->assertFalse($result['success'] ?? true);
    }

    public function testCartOfOneTenantIsNotCheckedOutInAnother(): void
    {
        $customer = $this->user(self::CUSTOMER_EMAIL);
        $this->createUserDomainRole($customer, $this->domain(self::TENANT_B_HOST));
        $product = $this->createTrackedProduct($this->domain(), 'Botas de Cuero Café', 13);
        $this->loginAs($customer);
        $this->assertTrue($this->addToCart((string) $product->getId(), 1)['success'] ?? false);

        $this->client->request('GET', self::TENANT_B_HOST . '/es/api/cart/count');
        $this->assertSame(0, (int) (json_decode((string) $this->client->getResponse()->getContent(), true)['count'] ?? -1));
    }

    public function testRepositoryQueriesAlwaysFilterByActiveDomain(): void
    {
        $repository = static::getContainer()->get(OrdersRepository::class);
        $domainB = $this->domain(self::TENANT_B_HOST);

        $count = $repository->getList($domainB, isCount: true) ?: [0];
        $this->assertSame(0, (int) reset($count));
        $this->assertSame([], $repository->getList($domainB, isCount: false));

        $kpi = $repository->getSalesKpiSummary($domainB, new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('+1 day'));
        $this->assertSame(0, $kpi['totalVentas']);
        $this->assertSame(0, $kpi['pedidosPendientesPago']);

        $orderA = $this->tenantAOrder('Enviado');
        $this->assertTrue($repository->belongsToDomain($orderA, $this->domain()));
        $this->assertFalse($repository->belongsToDomain($orderA, $domainB));
    }

    private function createUserDomainRole(Users $user, \App\Entity\Tenants\Domains\Domains $domain): void
    {
        $usersDomains = new \App\Entity\Users\UsersDomains();
        $usersDomains->add(new \App\ArgumentHandler\UsersDomainsArgument(['user' => $user, 'domain' => $domain, 'roles' => ['ROLE_USER']]));
        $this->em->persist($usersDomains);
        $this->em->flush();
    }
}
