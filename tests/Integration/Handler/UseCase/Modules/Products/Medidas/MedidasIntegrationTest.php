<?php

namespace App\Tests\Integration\Handler\UseCase\Modules\Products\Medidas;

use App\Entity\Products\Medidas\Medidas;
use App\Handler\UseCase\Modules\Products\Medidas\AddMedidasUseCase;
use App\Handler\UseCase\Modules\Products\Medidas\EditMedidasUseCase;
use App\Handler\UseCase\Modules\Products\Medidas\ListMedidasUseCase;
use App\Handler\UseCase\Modules\Products\Medidas\ToggleStatusMedidasUseCase;
use App\Interface\UseCase\Modules\Products\Medidas\AddMedidasInterface;
use App\Interface\UseCase\Modules\Products\Medidas\EditMedidasInterface;
use App\Interface\UseCase\Modules\Products\Medidas\ListMedidasInterface;
use App\Interface\UseCase\Modules\Products\Medidas\ToggleStatusMedidasInterface;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class MedidasIntegrationTest extends IntegrationTestCase
{

    public function testAllUseCasesAreRegisteredInContainer(): void
    {
        $this->assertInstanceOf(AddMedidasUseCase::class, static::getContainer()->get(AddMedidasInterface::class));
        $this->assertInstanceOf(EditMedidasUseCase::class, static::getContainer()->get(EditMedidasInterface::class));
        $this->assertInstanceOf(ListMedidasUseCase::class, static::getContainer()->get(ListMedidasInterface::class));
        $this->assertInstanceOf(ToggleStatusMedidasUseCase::class, static::getContainer()->get(ToggleStatusMedidasInterface::class));
    }

    public function testFixtureMedidasExistInDatabase(): void
    {
        $repo = $this->em->getRepository(Medidas::class);

        $m = $repo->findOneBy(['name' => 'M']);
        $l = $repo->findOneBy(['name' => 'L']);

        $this->assertNotNull($m, 'Fixture: "M" debe existir');
        $this->assertNotNull($l, 'Fixture: "L" debe existir');
        $this->assertTrue($m->isActive());
    }

    public function testGetRequestShowsAddFormWithoutSubmission(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(AddMedidasInterface::class);
        $result  = $service->handler();

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNotNull($result->getForm());
    }

    public function testAddCreatesMedidaOnValidPost(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/new', 'POST', [
            'medidas' => ['name' => 'XL'],
        ]));

        $service = static::getContainer()->get(AddMedidasInterface::class);
        $result  = $service->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $created = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'XL']);
        $this->assertNotNull($created, 'La entidad debe persistirse en la BD');
        $this->assertTrue($created->isActive());
    }

    public function testGetRequestShowsEditFormWithoutSubmission(): void
    {
        $medida = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'L']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(EditMedidasInterface::class);
        $result  = $service->handler($medida);

        $this->assertFalse($result->isProcess());
        $this->assertNotNull($result->getForm());
    }

    public function testEditUpdatesMedidaOnValidPost(): void
    {
        $medida = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'M']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'medidas' => ['name' => 'Mediana', 'activated' => true],
        ]));

        $service = static::getContainer()->get(EditMedidasInterface::class);
        $result  = $service->handler($medida);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $this->em->refresh($medida);
        $this->assertSame('Mediana', $medida->getName());

        $requestStack->push(Request::create('/edit', 'POST', [
            'medidas' => ['name' => 'M', 'activated' => true],
        ]));
        $service->handler($medida);
    }

    public function testEditWithActivatedFalseDeactivatesMedida(): void
    {
        $medida = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'L']);
        $this->assertTrue($medida->isActive(), 'Precondición: la entidad debe estar activa');

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'medidas' => ['name' => 'L', 'activated' => false],
        ]));

        $service = static::getContainer()->get(EditMedidasInterface::class);
        $service->handler($medida);

        $this->em->refresh($medida);
        $this->assertFalse($medida->isActive());

        $medida->activate();
        $this->em->flush();
    }

    public function testToggleStatusDeactivatesActiveMedida(): void
    {
        $medida = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'M']);
        $this->assertTrue($medida->isActive(), 'Precondición: la entidad debe estar activa');

        $service = static::getContainer()->get(ToggleStatusMedidasInterface::class);
        $result  = $service->handler($medida);

        $this->assertTrue($result['success']);
        $this->em->refresh($medida);
        $this->assertFalse($medida->isActive());
    }

    public function testToggleStatusActivatesInactiveMedida(): void
    {
        $medida = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'M']);
        $medida->deactivate();
        $this->em->flush();

        $service = static::getContainer()->get(ToggleStatusMedidasInterface::class);
        $result  = $service->handler($medida);

        $this->assertTrue($result['success']);
        $this->em->refresh($medida);
        $this->assertTrue($medida->isActive());
    }

    public function testListReturnsFixtureMedidas(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/list'));

        $service = static::getContainer()->get(ListMedidasInterface::class);
        $result  = $service->handler();

        $this->assertArrayHasKey('data', $result);
        $this->assertGreaterThanOrEqual(2, count($result['data']));
    }
}
