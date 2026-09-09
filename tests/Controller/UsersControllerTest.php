<?php

namespace App\Test\Controller\Users;

use App\Entity\Users\Users;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UsersControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $repository;
    private string $path = '/controller/dashboard/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->manager->getRepository(Users::class);

        foreach ($this->repository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User index');

        
        
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'user[email]' => 'Testing',
            'user[roles]' => 'Testing',
            'user[password]' => 'Testing',
            'user[name]' => 'Testing',
            'user[lastName]' => 'Testing',
            'user[dateOfBirth]' => 'Testing',
            'user[phone]' => 'Testing',
            'user[address]' => 'Testing',
            'user[profilePicture]' => 'Testing',
            'user[neighborhood]' => 'Testing',
            'user[prefix]' => 'Testing',
            'user[points]' => 'Testing',
            'user[validatedEmail]' => 'Testing',
            'user[active]' => 'Testing',
            'user[createdAt]' => 'Testing',
            'user[updatedAt]' => 'Testing',
            'user[domain]' => 'Testing',
            'user[country]' => 'Testing',
            'user[region]' => 'Testing',
            'user[city]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->repository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Users();
        $fixture->setEmail('My Title');
        $fixture->setRoles('My Title');
        $fixture->setPassword('My Title');
        $fixture->setName('My Title');
        $fixture->setLastName('My Title');
        $fixture->setDateOfBirth('My Title');
        $fixture->setPhone('My Title');
        $fixture->setAddress('My Title');
        $fixture->setProfilePicture('My Title');
        $fixture->setNeighborhood('My Title');
        $fixture->setPrefix('My Title');
        $fixture->setPoints('My Title');
        $fixture->setValidatedEmail('My Title');
        $fixture->setActive('My Title');
        $fixture->setCreatedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setDomain('My Title');
        $fixture->setCountry('My Title');
        $fixture->setRegion('My Title');
        $fixture->setCity('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User');

        
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Users();
        $fixture->setEmail('Value');
        $fixture->setRoles('Value');
        $fixture->setPassword('Value');
        $fixture->setName('Value');
        $fixture->setLastName('Value');
        $fixture->setDateOfBirth('Value');
        $fixture->setPhone('Value');
        $fixture->setAddress('Value');
        $fixture->setProfilePicture('Value');
        $fixture->setNeighborhood('Value');
        $fixture->setPrefix('Value');
        $fixture->setPoints('Value');
        $fixture->setValidatedEmail('Value');
        $fixture->setActive('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setUpdatedAt('Value');
        $fixture->setDomain('Value');
        $fixture->setCountry('Value');
        $fixture->setRegion('Value');
        $fixture->setCity('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'user[email]' => 'Something New',
            'user[roles]' => 'Something New',
            'user[password]' => 'Something New',
            'user[name]' => 'Something New',
            'user[lastName]' => 'Something New',
            'user[dateOfBirth]' => 'Something New',
            'user[phone]' => 'Something New',
            'user[address]' => 'Something New',
            'user[profilePicture]' => 'Something New',
            'user[neighborhood]' => 'Something New',
            'user[prefix]' => 'Something New',
            'user[points]' => 'Something New',
            'user[validatedEmail]' => 'Something New',
            'user[active]' => 'Something New',
            'user[createdAt]' => 'Something New',
            'user[updatedAt]' => 'Something New',
            'user[domain]' => 'Something New',
            'user[country]' => 'Something New',
            'user[region]' => 'Something New',
            'user[city]' => 'Something New',
        ]);

        self::assertResponseRedirects('/controller/dashboard/');

        $fixture = $this->repository->findAll();

        self::assertSame('Something New', $fixture[0]->getEmail());
        self::assertSame('Something New', $fixture[0]->getRoles());
        self::assertSame('Something New', $fixture[0]->getPassword());
        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getLastName());
        self::assertSame('Something New', $fixture[0]->getDateOfBirth());
        self::assertSame('Something New', $fixture[0]->getPhone());
        self::assertSame('Something New', $fixture[0]->getAddress());
        self::assertSame('Something New', $fixture[0]->getProfilePicture());
        self::assertSame('Something New', $fixture[0]->getNeighborhood());
        self::assertSame('Something New', $fixture[0]->getPrefix());
        self::assertSame('Something New', $fixture[0]->getPoints());
        self::assertSame('Something New', $fixture[0]->getValidatedEmail());
        self::assertSame('Something New', $fixture[0]->getActive());
        self::assertSame('Something New', $fixture[0]->getCreatedAt());
        self::assertSame('Something New', $fixture[0]->getUpdatedAt());
        self::assertSame('Something New', $fixture[0]->getDomain());
        self::assertSame('Something New', $fixture[0]->getCountry());
        self::assertSame('Something New', $fixture[0]->getRegion());
        self::assertSame('Something New', $fixture[0]->getCity());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Users();
        $fixture->setEmail('Value');
        $fixture->setRoles('Value');
        $fixture->setPassword('Value');
        $fixture->setName('Value');
        $fixture->setLastName('Value');
        $fixture->setDateOfBirth('Value');
        $fixture->setPhone('Value');
        $fixture->setAddress('Value');
        $fixture->setProfilePicture('Value');
        $fixture->setNeighborhood('Value');
        $fixture->setPrefix('Value');
        $fixture->setPoints('Value');
        $fixture->setValidatedEmail('Value');
        $fixture->setActive('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setUpdatedAt('Value');
        $fixture->setDomain('Value');
        $fixture->setCountry('Value');
        $fixture->setRegion('Value');
        $fixture->setCity('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/controller/dashboard/');
        self::assertSame(0, $this->repository->count([]));
    }
}
