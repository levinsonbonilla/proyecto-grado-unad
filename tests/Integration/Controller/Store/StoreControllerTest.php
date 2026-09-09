<?php

namespace App\Tests\Integration\Controller\Store;

use App\Entity\Products\Products;
use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StoreControllerTest extends WebTestCase
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

    public function testProductDetailReturns404ForOutOfStockProduct(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $client->followRedirects();

        $outOfStock = $em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test D (agotado)']);
        $this->assertNotNull($outOfStock);

        $client->request('GET', '/es/product/' . $outOfStock->getId(), server: self::HOST);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testProductDetailIsAccessibleForInStockProduct(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $client->followRedirects();

        $inStock = $em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test C (con stock)']);
        $this->assertNotNull($inStock);

        $client->request('GET', '/es/product/' . $inStock->getId(), server: self::HOST);

        $this->assertResponseIsSuccessful();
    }

    public function testGeneralProductFetchReturns404ForOutOfStockProduct(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $client->followRedirects();

        $outOfStock = $em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test D (agotado)']);
        $this->assertNotNull($outOfStock);

        $client->request('GET', '/es/general/product/' . $outOfStock->getId(), server: self::HOST);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGeneralProductFetchExposesStockForInStockProduct(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $client->followRedirects();

        $inStock = $em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test C (con stock)']);
        $this->assertNotNull($inStock);

        $client->request('GET', '/es/general/product/' . $inStock->getId(), server: self::HOST);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(5, $data['stock']);
    }

    public function testGuestHeaderShowsLoginAndRegisterIconsNotOldTopBar(): void
    {
        [$client] = $this->bootWithFixtures();
        $client->followRedirects();

        $crawler = $client->request('GET', '/es', server: self::HOST);

        $this->assertResponseIsSuccessful();

        $this->assertCount(0, $crawler->filter('.top-bar a'));
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('zmdi-sign-in', $content);
        $this->assertStringContainsString('zmdi-account-add', $content);
        $this->assertStringContainsString($client->getContainer()->get('router')->generate('security_register'), $content);
    }

    public function testLoggedInHeaderShowsAccountIconsNotOldTopBar(): void
    {
        [$client] = $this->bootWithFixtures();
        $client->followRedirects();

        $customer = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer);
        $client->loginUser($customer);

        $crawler = $client->request('GET', '/es', server: self::HOST);

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('.top-bar a'));
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('zmdi-shopping-basket', $content);
        $this->assertStringContainsString('zmdi-account"', $content);
        $this->assertStringContainsString('zmdi-power', $content);
    }

    public function testAboutPageNoLongerShowsLoremIpsum(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', '/es/about', server: self::HOST);

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringNotContainsStringIgnoringCase('lorem ipsum', $content);
    }

    public function testHomePageHasLanguageSwitcherLinkingToTheOtherLocale(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', '/es', server: self::HOST);

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $router  = $client->getContainer()->get('router');
        $this->assertStringContainsString($router->generate('public_lang', ['lang' => 'en']), $content);
    }
}
