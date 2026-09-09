<?php

namespace App\Tests\Integration\Handler\UseCase\Modules\Products\Categories;

use App\Entity\Products\Categories\Categories;
use App\Form\Modules\Products\CategoriesType;
use App\Handler\UseCase\Modules\Products\Categories\AddCategoriesUseCase;
use App\Handler\UseCase\Modules\Products\Categories\EditCategoriesUseCase;
use App\Handler\UseCase\Modules\Products\Categories\ListCategoriesUseCase;
use App\Handler\UseCase\Modules\Products\Categories\ToggleStatusCategoriesUseCase;
use App\Interface\UseCase\Modules\Products\Categories\AddCategoriesInterface;
use App\Interface\UseCase\Modules\Products\Categories\EditCategoriesInterface;
use App\Interface\UseCase\Modules\Products\Categories\ListCategoriesInterface;
use App\Interface\UseCase\Modules\Products\Categories\ToggleStatusCategoriesInterface;
use App\Repository\Configurations\Countries\CountriesCategoriesRepository;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class CategoriesIntegrationTest extends IntegrationTestCase
{

    public function testAllUseCasesAreRegisteredInContainer(): void
    {
        $this->assertInstanceOf(AddCategoriesUseCase::class, static::getContainer()->get(AddCategoriesInterface::class));
        $this->assertInstanceOf(EditCategoriesUseCase::class, static::getContainer()->get(EditCategoriesInterface::class));
        $this->assertInstanceOf(ListCategoriesUseCase::class, static::getContainer()->get(ListCategoriesInterface::class));
        $this->assertInstanceOf(ToggleStatusCategoriesUseCase::class, static::getContainer()->get(ToggleStatusCategoriesInterface::class));
    }

    public function testFixtureCategoriesExistInDatabase(): void
    {
        $repo = $this->em->getRepository(Categories::class);

        $electronica = $repo->findOneBy(['name' => 'Categoria Electronica']);
        $hogar       = $repo->findOneBy(['name' => 'Categoria Hogar']);

        $this->assertNotNull($electronica, 'Fixture: "Categoria Electronica" debe existir');
        $this->assertNotNull($hogar, 'Fixture: "Categoria Hogar" debe existir');
        $this->assertTrue($electronica->isActive());
    }

    public function testGetRequestShowsAddFormWithoutSubmission(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(AddCategoriesInterface::class);
        $result  = $service->handler();

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNotNull($result->getForm());
    }

    public function testAddCreatesCategoryOnValidPost(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/new', 'POST', [
            'categories' => ['name' => 'Categoria Deportes', 'description' => 'Categoria de prueba creada por el test'],
        ]));

        $service = static::getContainer()->get(AddCategoriesInterface::class);
        $result  = $service->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $created = $this->em->getRepository(Categories::class)->findOneBy(['name' => 'Categoria Deportes']);
        $this->assertNotNull($created, 'La entidad debe persistirse en la BD');
        $this->assertTrue($created->isActive());
        $this->assertSame('Categoria de prueba creada por el test', $created->getDescription());
    }

    public function testAddCreatesCategoryWithGeoSelection(): void
    {
        $countries = $this->em->getRepository(\App\Entity\Configurations\Globals\Countries::class)->findAll();
        $this->assertNotEmpty($countries, 'Fixture: debe existir al menos un país');
        $countryId = (string) $countries[0]->getId();

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/new', 'POST', [
            'categories' => [
                'name' => 'Categoria Geo',
                'description' => 'Categoria con país asignado',
                'countries' => [$countryId],
            ],
        ]));

        $service = static::getContainer()->get(AddCategoriesInterface::class);
        $service->handler();

        $created = $this->em->getRepository(Categories::class)->findOneBy(['name' => 'Categoria Geo']);
        $this->assertNotNull($created);

        $countriesRepo = static::getContainer()->get(CountriesCategoriesRepository::class);
        $ids = $countriesRepo->getSelectedIds($created, false);
        $this->assertContains($countryId, $ids);
    }

    public function testGetRequestShowsEditFormWithoutSubmission(): void
    {
        $category = $this->em->getRepository(Categories::class)->findOneBy(['name' => 'Categoria Electronica']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(EditCategoriesInterface::class);
        $result  = $service->handler($category);

        $this->assertFalse($result->isProcess());
        $this->assertNotNull($result->getForm());
    }

    public function testEditUpdatesCategoryNameOnValidPost(): void
    {
        $category = $this->em->getRepository(Categories::class)->findOneBy(['name' => 'Categoria Hogar']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'categories' => ['name' => 'Categoria Hogar actualizada', 'description' => 'Descripcion actualizada', 'activated' => true],
        ]));

        $service = static::getContainer()->get(EditCategoriesInterface::class);
        $result  = $service->handler($category);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $this->em->refresh($category);
        $this->assertSame('Categoria Hogar actualizada', $category->getName());
        $this->assertSame('Descripcion actualizada', $category->getDescription());
    }

    public function testEditWithEmptyGeoDeactivatesExistingJunctions(): void
    {
        $category = $this->em->getRepository(Categories::class)->findOneBy(['name' => 'Categoria Hogar']);
        $countriesRepo = static::getContainer()->get(CountriesCategoriesRepository::class);

        $countries = $this->em->getRepository(\App\Entity\Configurations\Globals\Countries::class)->findAll();
        $countryId = (string) $countries[0]->getId();

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'categories' => ['name' => 'Categoria Hogar', 'description' => 'Desc', 'activated' => true, 'countries' => [$countryId]],
        ]));
        static::getContainer()->get(EditCategoriesInterface::class)->handler($category);
        $this->assertNotEmpty($countriesRepo->getSelectedIds($category, false), 'Precondición: debe quedar un país asignado');

        $requestStack->push(Request::create('/edit', 'POST', [
            'categories' => ['name' => 'Categoria Hogar', 'description' => 'Desc', 'activated' => true, 'countries' => [], 'regions' => [], 'cities' => []],
        ]));
        static::getContainer()->get(EditCategoriesInterface::class)->handler($category);

        $ids = $countriesRepo->getSelectedIds($category, false);
        $this->assertEmpty($ids, 'Todos los vínculos de países deben desactivarse');
    }

    public function testToggleStatusDeactivatesActiveCategory(): void
    {
        $category = $this->em->getRepository(Categories::class)->findOneBy(['name' => 'Categoria Electronica']);
        $this->assertTrue($category->isActive(), 'Precondición: la entidad debe estar activa');

        $service = static::getContainer()->get(ToggleStatusCategoriesInterface::class);
        $result  = $service->handler($category);

        $this->assertTrue($result['success']);
        $this->em->refresh($category);
        $this->assertFalse($category->isActive());
    }

    public function testToggleStatusActivatesInactiveCategory(): void
    {
        $category = $this->em->getRepository(Categories::class)->findOneBy(['name' => 'Categoria Electronica']);
        $category->deactivate();
        $this->em->flush();

        $service = static::getContainer()->get(ToggleStatusCategoriesInterface::class);
        $result  = $service->handler($category);

        $this->assertTrue($result['success']);
        $this->em->refresh($category);
        $this->assertTrue($category->isActive());
    }

    public function testListReturnsFixtureCategories(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/list'));

        $service = static::getContainer()->get(ListCategoriesInterface::class);
        $result  = $service->handler();

        $this->assertArrayHasKey('data', $result);
        $this->assertGreaterThanOrEqual(2, count($result['data']));
    }
}
