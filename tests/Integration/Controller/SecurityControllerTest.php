<?php

namespace App\Tests\Integration\Controller;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    private function bootWithFixtures()
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());

        return $client;
    }

    public function testLoginPageHasBackToStoreLink(): void
    {
        $client = $this->bootWithFixtures();
        $client->followRedirects();
        $client->request('GET', 'http://localhost:8060/es/login');

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString(
            $client->getContainer()->get('router')->generate('store', ['_locale' => 'es']),
            $content
        );
    }

    public function testLoginPageHasLanguageSwitcherLinkingToTheOtherLocale(): void
    {
        $client = $this->bootWithFixtures();
        $client->followRedirects();
        $client->request('GET', 'http://localhost:8060/es/login');

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $router = $client->getContainer()->get('router');
        $this->assertStringContainsString($router->generate('public_lang', ['lang' => 'en']), $content);
    }

    public function testLoginLanguageSwitcherActuallySwitchesLocale(): void
    {
        $client = $this->bootWithFixtures();
        $client->followRedirects();

        $client->request('GET', 'http://localhost:8060/es/login');

        $client->request(
            'GET',
            'http://localhost:8060/es/public/lang/en',
            server: ['HTTP_REFERER' => 'http://localhost:8060/es/login']
        );

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('/en/login', $client->getRequest()->getUri());
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Login Form', $content);
    }
}
