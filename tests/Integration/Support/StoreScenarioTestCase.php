<?php

namespace App\Tests\Integration\Support;

use App\ArgumentHandler\ColorsArgument;
use App\ArgumentHandler\ProductColorArgument;
use App\ArgumentHandler\ProductsArgument;
use App\ArgumentHandler\UsersArgument;
use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Kernel;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

abstract class StoreScenarioTestCase extends WebTestCase
{
    protected const TENANT_A_HOST = 'http://localhost:8060';
    protected const TENANT_B_HOST = 'http://localhost:8090';
    protected const LOCALE = 'es';
    protected const CUSTOMER_EMAIL = 'customer.test@proyecto-grado.test';
    protected const ADMIN_EMAIL = 'admin.test@proyecto-grado.test';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    private array $secondaryKernels = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $classes = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
    }

    protected function tearDown(): void
    {
        foreach ($this->secondaryKernels as $kernel) {
            $kernel->shutdown();
        }
        $this->secondaryKernels = [];
        parent::tearDown();
    }

    protected function domain(string $host = self::TENANT_A_HOST): Domains
    {
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => $host]);
        $this->assertNotNull($domain, "El dominio {$host} debe existir en fixtures");
        return $domain;
    }

    protected function user(string $email): Users
    {
        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user, "El usuario {$email} debe existir");
        return $user;
    }

    protected function createUserForDomain(string $email, Domains $domain, array $roles): Users
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new Users($hasher);
        $user->add(new UsersArgument([
            'email' => $email,
            'name' => 'Usuario',
            'lastName' => 'Evaluacion',
            'password' => 'Evaluacion1*',
            'roles' => $roles,
        ], $domain));
        $user->validateEmail();
        $this->em->persist($user);

        $usersDomains = new UsersDomains();
        $usersDomains->add(new UsersDomainsArgument(['user' => $user, 'domain' => $domain, 'roles' => $roles]));
        $this->em->persist($usersDomains);
        $this->em->flush();
        $this->em->refresh($user);

        return $user;
    }

    protected function createTrackedProduct(Domains $domain, string $name, ?int $stock, string $price = '300000'): Products
    {
        $product = new Products();
        $product->add(new ProductsArgument([
            'name' => $name,
            'description' => 'Producto de evaluación',
            'basePrice' => $price,
            'publicPrice' => $price,
            'stock' => $stock === null ? null : (string) $stock,
        ], $domain));
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    protected function createVariantBlock(Products $product, string $colorName, int $stock): ProductsColors
    {
        $color = (new Colors())->add(new ColorsArgument(['name' => $colorName, 'hexCode' => '#6b4423'], $product->getDomain()));
        $this->em->persist($color);
        $block = (new ProductsColors())->add(new ProductColorArgument(['color' => $color, 'stock' => $stock], $product));
        $this->em->persist($block);
        $this->em->flush();

        return $block;
    }

    protected function createPaymentMethod(Domains $domain, string $name): PaymentMethods
    {
        $method = (new PaymentMethods())->add($domain, $name, PaymentMethods::PROVIDER_MANUAL);
        $this->em->persist($method);
        $this->em->flush();

        return $method;
    }

    protected function paymentMethodId(string $name = 'Efectivo', ?Domains $domain = null): string
    {
        $method = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => $name, 'domain' => $domain ?? $this->domain()]);
        $this->assertNotNull($method, "El método de pago {$name} debe existir");
        return (string) $method->getId();
    }

    protected function shippingData(?string $paymentMethodId): array
    {
        $country = $this->em->getRepository(Countries::class)->findOneBy(['isoCode' => 'CO']);
        $region = $this->em->getRepository(Regions::class)->findOneBy(['country' => $country], ['name' => 'ASC']);
        $city = $this->em->getRepository(Cities::class)->findOneBy(['region' => $region]);
        if ($city === null) {
            foreach ($this->em->getRepository(Regions::class)->findBy(['country' => $country]) as $candidate) {
                $city = $this->em->getRepository(Cities::class)->findOneBy(['region' => $candidate]);
                if ($city !== null) {
                    $region = $candidate;
                    break;
                }
            }
        }
        $this->assertNotNull($city, 'Las fixtures deben tener al menos una ciudad');

        return [
            'address' => 'Calle 10 # 5-20',
            'countryId' => (string) $country->getId(),
            'regionId' => (string) $region->getId(),
            'cityId' => (string) $city->getId(),
            'paymentMethodId' => $paymentMethodId ?? '',
        ];
    }

    protected function loginAs(Users $user, ?KernelBrowser $client = null): void
    {
        ($client ?? $this->client)->loginUser($user);
    }

    protected function postJson(string $url, array $payload, ?KernelBrowser $client = null): array
    {
        $client ??= $this->client;
        $client->request('POST', $url, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($payload));

        return json_decode((string) $client->getResponse()->getContent(), true) ?? [];
    }

    protected function addToCart(string $productId, int $quantity, ?string $colorId = null, string $host = self::TENANT_A_HOST, ?KernelBrowser $client = null): array
    {
        return $this->postJson(sprintf('%s/%s/cart/add', $host, self::LOCALE), [
            'productId' => $productId,
            'quantity' => $quantity,
            'colorId' => $colorId ?? '',
        ], $client);
    }

    protected function updateCart(string $cartItemId, int $quantity, string $host = self::TENANT_A_HOST): array
    {
        return $this->postJson(sprintf('%s/%s/cart/update', $host, self::LOCALE), [
            'cartItemId' => $cartItemId,
            'quantity' => $quantity,
        ]);
    }

    protected function confirmOrder(?string $paymentMethodId, string $host = self::TENANT_A_HOST, ?KernelBrowser $client = null): array
    {
        return $this->postJson(sprintf('%s/%s/checkout/confirm', $host, self::LOCALE), $this->shippingData($paymentMethodId), $client);
    }

    protected function cancelAsCustomer(string $orderId, string $host = self::TENANT_A_HOST): void
    {
        $this->client->request('POST', sprintf('%s/%s/orders/%s/cancel', $host, self::LOCALE, $orderId));
    }

    protected function freshProductStock(string $productId): ?int
    {
        $value = $this->em->getConnection()->fetchOne(
            'SELECT stock FROM products WHERE id = ?',
            [$this->binaryId($productId)]
        );
        return $value === null || $value === false ? null : (int) $value;
    }

    protected function freshBlockStock(string $blockId): ?int
    {
        $value = $this->em->getConnection()->fetchOne(
            'SELECT stock FROM products_colors WHERE id = ?',
            [$this->binaryId($blockId)]
        );
        return $value === null || $value === false ? null : (int) $value;
    }

    protected function freshOrderStatus(string $orderId): string
    {
        $this->em->clear();
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $this->assertNotNull($order);
        return $order->getStatus()->getName();
    }

    protected function countOrdersOf(Users $user): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM orders WHERE user_id = ?',
            [$this->binaryId((string) $user->getId())]
        );
    }

    protected function cartQuantity(Users $user, Products $product): ?int
    {
        $value = $this->em->getConnection()->fetchOne(
            'SELECT quantity FROM shopping_cart WHERE user_id = ? AND product_id = ? AND active = 1',
            [$this->binaryId((string) $user->getId()), $this->binaryId((string) $product->getId())]
        );
        return $value === null || $value === false ? null : (int) $value;
    }

    protected function cartItemId(Users $user, Products $product): string
    {
        $this->em->clear();
        $item = $this->em->getRepository(\App\Entity\Products\Others\ShoppingCart::class)->findOneBy([
            'user' => $user->getId(),
            'product' => $product->getId(),
            'active' => true,
        ]);
        $this->assertNotNull($item, 'El ítem debe existir en el carrito');
        return (string) $item->getId();
    }

    protected function binaryId(string $uuid): string
    {
        return \Symfony\Component\Uid\Uuid::fromString($uuid)->toBinary();
    }

    protected function bootSecondaryContainer(): ContainerInterface
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();
        $this->secondaryKernels[] = $kernel;

        return $kernel->getContainer()->get('test.service_container');
    }

    protected function actAsIn(ContainerInterface $container, Users $user, string $host = self::TENANT_A_HOST, string $path = '/es/checkout/confirm'): void
    {
        $request = Request::create($host . $path, 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->setLocale(self::LOCALE);
        $container->get('request_stack')->push($request);

        $localUser = $container->get(EntityManagerInterface::class)->getRepository(Users::class)->find($user->getId());
        $token = new UsernamePasswordToken($localUser, 'main', $localUser->getRoles());
        $container->get('security.token_storage')->setToken($token);
    }
}
