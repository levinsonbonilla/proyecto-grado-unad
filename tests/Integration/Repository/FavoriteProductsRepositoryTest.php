<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Products\Others\FavoriteProducts;
use App\Entity\Users\Users;
use App\Repository\Products\Others\FavoriteProductsRepository;
use App\Tests\Integration\IntegrationTestCase;

class FavoriteProductsRepositoryTest extends IntegrationTestCase
{
    private FavoriteProductsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(FavoriteProductsRepository::class);
    }

    public function testGetActiveForUserReturnsTheFixtureFavorite(): void
    {

        $customer = $this->em->getRepository(Users::class)
            ->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer, 'El usuario de fixture customer.test@proyecto-grado.test debe existir');

        $result = $this->repository->getActiveForUser($customer);

        $this->assertCount(1, $result);
        $this->assertSame('Producto Test A', $result[0]['name']);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('publicPrice', $result[0]);
    }

    public function testGetActiveForUserReturnsEmptyArrayForUserWithoutFavorites(): void
    {

        $superAdmin = $this->em->getRepository(Users::class)
            ->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $this->assertNotNull($superAdmin);

        $result = $this->repository->getActiveForUser($superAdmin);

        $this->assertSame([], $result);
    }

    public function testGetActiveForUserExcludesDeactivatedFavorites(): void
    {
        $customer = $this->em->getRepository(Users::class)
            ->findOneBy(['email' => 'customer.test@proyecto-grado.test']);

        $favorite = $this->em->getRepository(FavoriteProducts::class)
            ->findOneBy(['user' => $customer]);
        $this->assertNotNull($favorite);

        $favorite->deactivate();
        $this->em->flush();

        $result = $this->repository->getActiveForUser($customer);

        $this->assertSame([], $result);
    }
}
