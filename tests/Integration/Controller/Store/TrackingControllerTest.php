<?php

namespace App\Tests\Integration\Controller\Store;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrackingControllerTest extends WebTestCase
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

        $client->request('GET', '/es', server: self::HOST);

        return [$client, $em];
    }

    public function testEmptyEventNameReturns400(): void
    {
        [$client] = $this->bootWithFixtures();

        $client->request(
            'POST',
            '/es/api/track/event',
            server: array_merge(self::HOST, ['CONTENT_TYPE' => 'application/json']),
            content: json_encode(['eventName' => '']),
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testEventNameOver100CharsReturns400(): void
    {
        [$client] = $this->bootWithFixtures();

        $client->request(
            'POST',
            '/es/api/track/event',
            server: array_merge(self::HOST, ['CONTENT_TYPE' => 'application/json']),
            content: json_encode(['eventName' => str_repeat('a', 101)]),
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMissingEventNameReturns400(): void
    {
        [$client] = $this->bootWithFixtures();

        $client->request(
            'POST',
            '/es/api/track/event',
            server: array_merge(self::HOST, ['CONTENT_TYPE' => 'application/json']),
            content: json_encode(['metadata' => ['foo' => 'bar']]),
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRouteOnlyAcceptsPost(): void
    {
        [$client] = $this->bootWithFixtures();

        $client->request('GET', '/es/api/track/event', server: self::HOST);

        $this->assertResponseStatusCodeSame(405);
    }
}
