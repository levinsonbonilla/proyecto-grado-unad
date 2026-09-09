<?php

namespace App\Tests\Integration\Controller\Store;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Others\ShoppingCart;
use App\Entity\Products\Products;
use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CheckoutStockAndLowStockAlertTest extends WebTestCase
{
    private const HOST = ['HTTP_HOST' => 'localhost:8060'];

    private function bootWithFixtures(): array
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());

        return [$client, $em];
    }

    private function seedCartForTrackedProductWithColorBlock(EntityManagerInterface $em, int $quantity = 1): array
    {
        $customer = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer);

        $product = $em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test C (con stock)']);
        $this->assertNotNull($product, 'fixture de products.yaml[2] debe existir');

        $rojoBlock = null;
        foreach ($em->getRepository(ProductsColors::class)->findBy(['product' => $product]) as $block) {
            if ($block->getColor()?->getName() === 'Rojo') {
                $rojoBlock = $block;
                break;
            }
        }
        $this->assertNotNull($rojoBlock, 'fixture de createVariantBlockOrderScenario() debe existir (bloque Rojo de products[2])');

        $cartItem = (new ShoppingCart())->add($customer, $product, $quantity, $rojoBlock);
        $em->persist($cartItem);
        $em->flush();

        return [$customer, $product, $rojoBlock];
    }

    private function checkoutData(EntityManagerInterface $em): array
    {
        $country = $em->getRepository(Countries::class)->findAll()[0];
        $region  = $em->getRepository(Regions::class)->findAll()[0];
        $city    = $em->getRepository(Cities::class)->findAll()[0];

        return [
            'address'   => 'Calle 123 #45-67',
            'countryId' => (string) $country->getId(),
            'regionId'  => (string) $region->getId(),
            'cityId'    => (string) $city->getId(),
        ];
    }

    public function testConfirmDecreasesBothGeneralAndBlockStock(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        [$customer, $product, $rojoBlock] = $this->seedCartForTrackedProductWithColorBlock($em, quantity: 1);

        $generalStockBefore = $product->getStock();
        $blockStockBefore   = $rojoBlock->getStock();

        $client->loginUser($customer);
        $client->request(
            'POST',
            '/es/checkout/confirm',
            server: array_merge(self::HOST, ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json']),
            content: json_encode($this->checkoutData($em)),
        );

        $this->assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($result['success'] ?? false, 'checkout debió confirmarse: ' . json_encode($result));

        $em->clear();
        $productAfter = $em->getRepository(Products::class)->find($product->getId());
        $blockAfter   = $em->getRepository(ProductsColors::class)->find($rojoBlock->getId());

        $this->assertSame($generalStockBefore - 1, $productAfter->getStock(), 'el stock general del producto debe descontarse aunque haya color elegido');
        $this->assertSame($blockStockBefore - 1, $blockAfter->getStock(), 'el stock del bloque de variante debe seguir descontándose como siempre');
    }

    public function testConfirmMarksLowStockAlertFlagWhenGeneralStockCrossesThreshold(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        [$customer, $product, $rojoBlock] = $this->seedCartForTrackedProductWithColorBlock($em, quantity: 1);

        $this->assertFalse($product->hasLowStockAlertSent());

        $client->loginUser($customer);
        $client->request(
            'POST',
            '/es/checkout/confirm',
            server: array_merge(self::HOST, ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json']),
            content: json_encode($this->checkoutData($em)),
        );

        $this->assertResponseIsSuccessful();

        $em->clear();
        $productAfter = $em->getRepository(Products::class)->find($product->getId());
        $this->assertTrue($productAfter->hasLowStockAlertSent(), 'stock general bajó a 4 (≤5) -- el flag debe quedar marcado');
    }

    public function testConfirmMarksLowStockAlertFlagForBlockWhenItsOwnThresholdIsCrossed(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        [$customer, $product, $rojoBlock] = $this->seedCartForTrackedProductWithColorBlock($em, quantity: 2);

        $this->assertFalse($rojoBlock->hasLowStockAlertSent());

        $client->loginUser($customer);
        $client->request(
            'POST',
            '/es/checkout/confirm',
            server: array_merge(self::HOST, ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json']),
            content: json_encode($this->checkoutData($em)),
        );

        $this->assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($result['success'] ?? false, 'checkout debió confirmarse: ' . json_encode($result));

        $em->clear();
        $blockAfter = $em->getRepository(ProductsColors::class)->find($rojoBlock->getId());
        $this->assertSame(1, $blockAfter->getStock());
        $this->assertTrue($blockAfter->hasLowStockAlertSent(), 'stock del bloque bajó a 1 (≤1) -- el flag debe quedar marcado');
    }
}
