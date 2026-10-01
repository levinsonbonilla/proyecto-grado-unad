<?php

namespace App\Tests\Integration\Controller\Dashboard;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Others\AboutSections;
use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AboutAndContactSettingsControllerTest extends WebTestCase
{
    private const BASE = 'http://localhost:8060/es/dashboard/configurations';

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

    private function loginAs(KernelBrowser $client, string $email): void
    {
        $user = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $client->loginUser($user);
    }

    private function createSection(KernelBrowser $client, string $title, string $text, string $position = ''): void
    {
        $crawler = $client->request('GET', self::BASE . '/about/new');
        $form = $crawler->selectButton('send')->form([
            'about_sections[title]' => $title,
            'about_sections[text]' => $text,
            'about_sections[position]' => $position,
        ]);
        $client->submit($form);
    }

    private function sections(EntityManagerInterface $em): array
    {
        $em->clear();
        return $em->getRepository(AboutSections::class)->findBy([], ['position' => 'ASC']);
    }

    public function testAboutListRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', self::BASE . '/about');

        $this->assertStringContainsString('login', $client->getRequest()->getUri());
    }

    public function testContactSettingsRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', self::BASE . '/contact');

        $this->assertStringContainsString('login', $client->getRequest()->getUri());
    }

    public function testCustomerCannotAccessAboutSettings(): void
    {
        [$client] = $this->bootWithFixtures();
        $this->loginAs($client, 'customer.test@proyecto-grado.test');

        $client->request('GET', self::BASE . '/about');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCustomerCannotAccessContactSettings(): void
    {
        [$client] = $this->bootWithFixtures();
        $this->loginAs($client, 'customer.test@proyecto-grado.test');

        $client->request('GET', self::BASE . '/contact');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminSeesAboutListAndMenuEntries(): void
    {
        [$client] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $crawler = $client->request('GET', self::BASE . '/about');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#datatable-about-sections'));
        $this->assertCount(1, $crawler->filter('a[href$="/dashboard/configurations/about"]'));
        $this->assertCount(1, $crawler->filter('a[href$="/dashboard/configurations/contact"]'));
    }

    public function testCreateSectionPersistsItForTheCurrentDomain(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $this->createSection($client, 'Nuestra historia', "Primer párrafo\n\nSegundo párrafo");

        $sections = $this->sections($em);
        $this->assertCount(1, $sections);
        $this->assertSame('Nuestra historia', $sections[0]->getTitle());
        $this->assertSame("Primer párrafo\n\nSegundo párrafo", $sections[0]->getText());
        $this->assertNull($sections[0]->getImage());
        $this->assertTrue($sections[0]->isActive());
        $this->assertSame('http://localhost:8060', $sections[0]->getDomain()->getDomain());
    }

    public function testCreatedSectionsGetConsecutivePositionsWhenLeftEmpty(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $this->createSection($client, 'Uno', 'Texto uno');
        $this->createSection($client, 'Dos', 'Texto dos');
        $this->createSection($client, 'Tres', 'Texto tres');

        $this->assertSame(
            [['Uno', 1], ['Dos', 2], ['Tres', 3]],
            array_map(fn (AboutSections $s) => [$s->getTitle(), $s->getPosition()], $this->sections($em))
        );
    }

    public function testCreateSectionRespectsExplicitPosition(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $this->createSection($client, 'Al inicio', 'Texto', '0');
        $this->createSection($client, 'Al final', 'Texto', '9');

        $this->assertSame(
            [['Al inicio', 0], ['Al final', 9]],
            array_map(fn (AboutSections $s) => [$s->getTitle(), $s->getPosition()], $this->sections($em))
        );
    }

    public function testCreateSectionIsRejectedWithoutTitle(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $this->createSection($client, '', 'Texto sin título');

        $this->assertResponseStatusCodeSame(422);
        $this->assertCount(0, $this->sections($em));
    }

    public function testCreateSectionIsRejectedWithoutText(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $this->createSection($client, 'Título sin texto', '');

        $this->assertResponseStatusCodeSame(422);
        $this->assertCount(0, $this->sections($em));
    }

    public function testListEndpointReturnsOnlySectionsOfTheCurrentDomain(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');
        $this->createSection($client, 'De mi tienda', 'Texto');

        $otherDomain = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8090']);
        $foreign = new AboutSections();
        $foreign->add(new \App\ArgumentHandler\AboutSectionsArgument(['title' => 'De otra tienda', 'text' => 'Texto'], $otherDomain));
        $em->persist($foreign);
        $em->flush();

        $client->request('GET', self::BASE . '/about/list');

        $this->assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true);
        $titles = array_column($payload['data'], 'title');
        $this->assertSame(['De mi tienda'], $titles);
    }

    public function testEditSectionUpdatesItsFields(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');
        $this->createSection($client, 'Original', 'Texto original');
        $section = $this->sections($em)[0];

        $crawler = $client->request('GET', self::BASE . '/about/' . $section->getId() . '/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSame('Original', $crawler->filter('#about_sections_title')->attr('value'));

        $form = $crawler->selectButton('send')->form([
            'about_sections[title]' => 'Editada',
            'about_sections[text]' => 'Texto editado',
            'about_sections[position]' => '4',
        ]);
        $client->submit($form);

        $updated = $this->sections($em)[0];
        $this->assertSame('Editada', $updated->getTitle());
        $this->assertSame('Texto editado', $updated->getText());
        $this->assertSame(4, $updated->getPosition());
    }

    public function testToggleStatusHidesAndShowsTheSectionInTheStore(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');
        $this->createSection($client, 'Sección alternable', 'Texto');
        $section = $this->sections($em)[0];

        $client->request('POST', self::BASE . '/about/' . $section->getId() . '/toggle-status');
        $this->assertSame(['success' => true], json_decode($client->getResponse()->getContent(), true));
        $this->assertFalse($this->sections($em)[0]->isActive());

        $crawler = $client->request('GET', 'http://localhost:8060/es/about');
        $this->assertStringNotContainsString('Sección alternable', $crawler->text());

        $client->request('POST', self::BASE . '/about/' . $section->getId() . '/toggle-status');
        $this->assertTrue($this->sections($em)[0]->isActive());

        $crawler = $client->request('GET', 'http://localhost:8060/es/about');
        $this->assertStringContainsString('Sección alternable', $crawler->text());
    }

    public function testNewSectionAppearsInTheStoreRightAway(): void
    {
        [$client] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $client->request('GET', 'http://localhost:8060/es/about');
        $this->createSection($client, 'Recién creada', 'Texto');

        $crawler = $client->request('GET', 'http://localhost:8060/es/about');
        $this->assertStringContainsString('Recién creada', $crawler->text());
    }

    public function testContactFormIsPrefilledWithSavedData(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');
        $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060'])
            ->changeContact('Calle 1', '123', 'a@b.com');
        $em->flush();

        $crawler = $client->request('GET', self::BASE . '/contact');

        $this->assertResponseIsSuccessful();
        $this->assertSame('Calle 1', trim($crawler->filter('#contact_address')->text()));
        $this->assertSame('123', $crawler->filter('#contact_phone')->attr('value'));
        $this->assertSame('a@b.com', $crawler->filter('#contact_email')->attr('value'));
    }

    public function testSavingContactUpdatesTheDomainAndTheStorePage(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $client->request('GET', 'http://localhost:8060/es/contact');

        $crawler = $client->request('GET', self::BASE . '/contact');
        $form = $crawler->selectButton('send')->form([
            'contact[address]' => 'Avenida 68 # 10-20',
            'contact[phone]' => '+57 601 555 0101',
            'contact[email]' => 'hola@mitienda.com',
        ]);
        $client->submit($form);

        $em->clear();
        $domain = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $this->assertSame('Avenida 68 # 10-20', $domain->getContactAddress());
        $this->assertSame('+57 601 555 0101', $domain->getContactPhone());
        $this->assertSame('hola@mitienda.com', $domain->getContactEmail());

        $page = $client->request('GET', 'http://localhost:8060/es/contact');
        $this->assertStringContainsString('Avenida 68 # 10-20', $page->text());
        $this->assertStringContainsString('hola@mitienda.com', $page->text());
    }

    public function testSavingContactDoesNotTouchOtherDomains(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $crawler = $client->request('GET', self::BASE . '/contact');
        $client->submit($crawler->selectButton('send')->form(['contact[address]' => 'Solo para tienda uno']));

        $em->clear();
        $other = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8090']);
        $this->assertNull($other->getContactAddress());
    }

    public function testSavingEmptyContactClearsPreviousData(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');
        $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060'])
            ->changeContact('Calle 1', '123', 'a@b.com');
        $em->flush();

        $crawler = $client->request('GET', self::BASE . '/contact');
        $client->submit($crawler->selectButton('send')->form([
            'contact[address]' => '',
            'contact[phone]' => '',
            'contact[email]' => '',
        ]));

        $em->clear();
        $domain = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $this->assertNull($domain->getContactAddress());
        $this->assertNull($domain->getContactPhone());
        $this->assertNull($domain->getContactEmail());
    }

    public function testSavingContactWithInvalidEmailIsRejected(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $this->loginAs($client, 'admin.test@proyecto-grado.test');

        $crawler = $client->request('GET', self::BASE . '/contact');
        $client->submit($crawler->selectButton('send')->form([
            'contact[address]' => 'Calle 9',
            'contact[email]' => 'esto-no-es-un-correo',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $em->clear();
        $domain = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $this->assertNull($domain->getContactAddress());
        $this->assertNull($domain->getContactEmail());
    }
}
