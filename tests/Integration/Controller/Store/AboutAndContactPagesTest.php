<?php

namespace App\Tests\Integration\Controller\Store;

use App\ArgumentHandler\AboutSectionsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Others\AboutSections;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AboutAndContactPagesTest extends WebTestCase
{
    private const HOST = ['HTTP_HOST' => 'localhost:8060'];
    private const OTHER_HOST = ['HTTP_HOST' => 'localhost:8090'];

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

    private function domain(EntityManagerInterface $em, string $url): Domains
    {
        return $em->getRepository(Domains::class)->findOneBy(['domain' => $url]);
    }

    private function addSection(EntityManagerInterface $em, Domains $domain, string $title, string $text, ?string $image, int $position, bool $active = true): void
    {
        $section = (new AboutSections())->add(new AboutSectionsArgument([
            'title' => $title,
            'text' => $text,
            'image' => $image,
            'position' => $position,
        ], $domain));
        if (!$active) {
            $section->deactivate();
        }
        $em->persist($section);
        $em->flush();
    }

    public function testAboutPageShowsEmptyMessageWhenNoSectionsAreConfigured(): void
    {
        [$client] = $this->bootWithFixtures();

        $crawler = $client->request('GET', '/es/about', server: self::HOST);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Pronto compartiremos', $crawler->text());
    }

    public function testAboutPageRendersSectionsInPositionOrder(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $domain = $this->domain($em, 'http://localhost:8060');
        $this->addSection($em, $domain, 'Segunda sección', 'Texto dos', 'https://cdn.example.com/2.jpg', 2);
        $this->addSection($em, $domain, 'Primera sección', 'Texto uno', 'https://cdn.example.com/1.jpg', 1);

        $client->request('GET', '/es/about', server: self::HOST);

        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        $this->assertLessThan(strpos($html, 'Segunda sección'), strpos($html, 'Primera sección'));
        $this->assertStringNotContainsString('Pronto compartiremos', $html);
    }

    public function testAboutPageAlternatesImageSide(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $domain = $this->domain($em, 'http://localhost:8060');
        $this->addSection($em, $domain, 'Primera', 'Uno', 'https://cdn.example.com/1.jpg', 1);
        $this->addSection($em, $domain, 'Segunda', 'Dos', 'https://cdn.example.com/2.jpg', 2);
        $this->addSection($em, $domain, 'Tercera', 'Tres', 'https://cdn.example.com/3.jpg', 3);

        $crawler = $client->request('GET', '/es/about', server: self::HOST);

        $this->assertCount(2, $crawler->filter('.how-bor1'));
        $this->assertCount(1, $crawler->filter('.how-bor2'));
        $rows = $crawler->filter('section.bg0 .container > .row');
        $this->assertCount(3, $rows);
        $this->assertCount(1, $rows->eq(0)->filter('.how-bor1'));
        $this->assertCount(1, $rows->eq(1)->filter('.how-bor2'));
        $this->assertCount(1, $rows->eq(2)->filter('.how-bor1'));
    }

    public function testAboutPageRendersSectionWithoutImageAsFullWidthText(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $domain = $this->domain($em, 'http://localhost:8060');
        $this->addSection($em, $domain, 'Solo texto', 'Contenido sin imagen', null, 1);

        $crawler = $client->request('GET', '/es/about', server: self::HOST);

        $this->assertStringContainsString('Solo texto', $crawler->text());
        $this->assertCount(0, $crawler->filter('.how-bor1, .how-bor2'));
        $this->assertCount(1, $crawler->filter('.row > .col-12'));
    }

    public function testAboutPageSplitsParagraphsOnBlankLines(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $domain = $this->domain($em, 'http://localhost:8060');
        $this->addSection($em, $domain, 'Párrafos', "Uno\n\nDos\n\nTres", null, 1);

        $crawler = $client->request('GET', '/es/about', server: self::HOST);

        $this->assertCount(3, $crawler->filter('section.bg0 p.stext-113'));
    }

    public function testAboutPageEscapesHtmlInSections(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $domain = $this->domain($em, 'http://localhost:8060');
        $this->addSection($em, $domain, '<script>alert(1)</script>', '<b>negrita</b>', null, 1);

        $client->request('GET', '/es/about', server: self::HOST);

        $html = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>negrita</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;negrita&lt;/b&gt;', $html);
    }

    public function testAboutPageHidesInactiveSections(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $domain = $this->domain($em, 'http://localhost:8060');
        $this->addSection($em, $domain, 'Visible', 'Uno', null, 1);
        $this->addSection($em, $domain, 'Oculta', 'Dos', null, 2, false);

        $crawler = $client->request('GET', '/es/about', server: self::HOST);

        $this->assertStringContainsString('Visible', $crawler->text());
        $this->assertStringNotContainsString('Oculta', $crawler->text());
    }

    public function testAboutPageOnlyShowsSectionsOfTheCurrentDomain(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->addSection($em, $this->domain($em, 'http://localhost:8060'), 'Sección tienda uno', 'Uno', null, 1);
        $this->addSection($em, $this->domain($em, 'http://localhost:8090'), 'Sección tienda dos', 'Dos', null, 1);

        $crawler = $client->request('GET', '/es/about', server: self::OTHER_HOST);

        $this->assertStringContainsString('Sección tienda dos', $crawler->text());
        $this->assertStringNotContainsString('Sección tienda uno', $crawler->text());
    }

    public function testContactPageShowsConfiguredData(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->domain($em, 'http://localhost:8060')->changeContact('Calle 85 # 15-30', '+57 300 123 4567', 'ventas@mitienda.com');
        $em->flush();

        $crawler = $client->request('GET', '/es/contact', server: self::HOST);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Calle 85 # 15-30', $crawler->text());
        $this->assertCount(1, $crawler->filter('a[href="tel:+573001234567"]'));
        $this->assertCount(1, $crawler->filter('a[href="mailto:ventas@mitienda.com"]'));
    }

    public function testContactPageHidesFieldsThatAreNotConfigured(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->domain($em, 'http://localhost:8060')->changeContact(null, '3001234567', null);
        $em->flush();

        $crawler = $client->request('GET', '/es/contact', server: self::HOST);

        $this->assertCount(1, $crawler->filter('a[href^="tel:"]'));
        $this->assertCount(0, $crawler->filter('a[href^="mailto:"]'));
        $this->assertCount(0, $crawler->filter('.lnr-map-marker'));
        $this->assertCount(0, $crawler->filter('.lnr-envelope'));
    }

    public function testContactPageShowsOnlyTheFormWhenNoDataIsConfigured(): void
    {
        [$client] = $this->bootWithFixtures();

        $crawler = $client->request('GET', '/es/contact', server: self::HOST);

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('section.bg0 form'));
        $this->assertCount(0, $crawler->filter('.lnr-map-marker, .lnr-phone-handset, .lnr-envelope'));
    }

    public function testContactPageNoLongerLoadsTheMap(): void
    {
        [$client] = $this->bootWithFixtures();

        $client->request('GET', '/es/contact', server: self::HOST);

        $html = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('google_map', $html);
        $this->assertStringNotContainsString('maps.googleapis.com', $html);
        $this->assertStringNotContainsString('Coza Store', $html);
    }

    public function testContactDataOfOneDomainDoesNotLeakToAnother(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->domain($em, 'http://localhost:8060')->changeContact('Dirección privada uno', '111', 'uno@example.com');
        $em->flush();

        $crawler = $client->request('GET', '/es/contact', server: self::OTHER_HOST);

        $this->assertStringNotContainsString('Dirección privada uno', $crawler->text());
        $this->assertStringNotContainsString('uno@example.com', $client->getResponse()->getContent());
    }

    public function testFooterShowsConfiguredAddressAndPhone(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->domain($em, 'http://localhost:8060')->changeContact('Carrera 7 # 1-2', '3105550000', null);
        $em->flush();

        $crawler = $client->request('GET', '/es/about', server: self::HOST);

        $footer = $crawler->filter('footer')->text();
        $this->assertStringContainsString('Carrera 7 # 1-2', $footer);
        $this->assertStringContainsString('3105550000', $footer);
        $this->assertStringNotContainsString('Hudson', $footer);
    }
}
