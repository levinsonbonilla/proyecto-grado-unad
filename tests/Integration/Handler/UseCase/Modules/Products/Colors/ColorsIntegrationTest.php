<?php

namespace App\Tests\Integration\Handler\UseCase\Modules\Products\Colors;

use App\Entity\Products\Colors\Colors;
use App\Form\Modules\Products\ColorsType;
use App\Handler\UseCase\Modules\Products\Colors\AddColorsUseCase;
use App\Handler\UseCase\Modules\Products\Colors\EditColorsUseCase;
use App\Handler\UseCase\Modules\Products\Colors\ListColorsUseCase;
use App\Handler\UseCase\Modules\Products\Colors\ToggleStatusColorsUseCase;
use App\Interface\UseCase\Modules\Products\Colors\AddColorsInterface;
use App\Interface\UseCase\Modules\Products\Colors\EditColorsInterface;
use App\Interface\UseCase\Modules\Products\Colors\ListColorsInterface;
use App\Interface\UseCase\Modules\Products\Colors\ToggleStatusColorsInterface;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class ColorsIntegrationTest extends IntegrationTestCase
{

    public function testAllUseCasesAreRegisteredInContainer(): void
    {
        $this->assertInstanceOf(AddColorsUseCase::class, static::getContainer()->get(AddColorsInterface::class));
        $this->assertInstanceOf(EditColorsUseCase::class, static::getContainer()->get(EditColorsInterface::class));
        $this->assertInstanceOf(ListColorsUseCase::class, static::getContainer()->get(ListColorsInterface::class));
        $this->assertInstanceOf(ToggleStatusColorsUseCase::class, static::getContainer()->get(ToggleStatusColorsInterface::class));
    }

    public function testFixtureColorsExistInDatabase(): void
    {
        $repo = $this->em->getRepository(Colors::class);

        $negro = $repo->findOneBy(['name' => 'Negro']);
        $rojo  = $repo->findOneBy(['name' => 'Rojo']);
        $azul  = $repo->findOneBy(['name' => 'Azul']);

        $this->assertNotNull($negro, 'Fixture: "Negro" debe existir');
        $this->assertNotNull($rojo, 'Fixture: "Rojo" debe existir');
        $this->assertNotNull($azul, 'Fixture: "Azul" debe existir');
        $this->assertTrue($negro->isActive());
        $this->assertSame('#1a1a1a', $negro->getHexCode());
    }

    public function testGetRequestShowsAddFormWithoutSubmission(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(AddColorsInterface::class);
        $result  = $service->handler(ColorsType::class);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNotNull($result->getForm());
    }

    public function testAddCreatesColorOnValidPost(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/new', 'POST', [
            'colors' => ['name' => 'Verde', 'hexCode' => '#2e7d32'],
        ]));

        $service = static::getContainer()->get(AddColorsInterface::class);
        $result  = $service->handler(ColorsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $created = $this->em->getRepository(Colors::class)->findOneBy(['name' => 'Verde']);
        $this->assertNotNull($created, 'La entidad debe persistirse en la BD');
        $this->assertTrue($created->isActive());
        $this->assertSame('#2e7d32', $created->getHexCode());
    }

    public function testGetRequestShowsEditFormWithoutSubmission(): void
    {
        $color = $this->em->getRepository(Colors::class)->findOneBy(['name' => 'Azul']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(EditColorsInterface::class);
        $result  = $service->handler($color);

        $this->assertFalse($result->isProcess());
        $this->assertNotNull($result->getForm());
    }

    public function testEditUpdatesColorOnValidPost(): void
    {
        $color = $this->em->getRepository(Colors::class)->findOneBy(['name' => 'Rojo']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'colors' => ['name' => 'Rojo intenso', 'hexCode' => '#c62828', 'activated' => true],
        ]));

        $service = static::getContainer()->get(EditColorsInterface::class);
        $result  = $service->handler($color);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $this->em->refresh($color);
        $this->assertSame('Rojo intenso', $color->getName());
        $this->assertSame('#c62828', $color->getHexCode());
    }

    public function testEditWithActivatedFalseDeactivatesColor(): void
    {
        $color = $this->em->getRepository(Colors::class)->findOneBy(['name' => 'Azul']);
        $this->assertTrue($color->isActive(), 'Precondición: la entidad debe estar activa');

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'colors' => ['name' => 'Azul', 'hexCode' => '#1e88e5', 'activated' => false],
        ]));

        $service = static::getContainer()->get(EditColorsInterface::class);
        $service->handler($color);

        $this->em->refresh($color);
        $this->assertFalse($color->isActive());
    }

    public function testToggleStatusDeactivatesActiveColor(): void
    {
        $color = $this->em->getRepository(Colors::class)->findOneBy(['name' => 'Negro']);
        $this->assertTrue($color->isActive(), 'Precondición: la entidad debe estar activa');

        $service = static::getContainer()->get(ToggleStatusColorsInterface::class);
        $result  = $service->handler($color);

        $this->assertTrue($result['success']);
        $this->em->refresh($color);
        $this->assertFalse($color->isActive());
    }

    public function testToggleStatusActivatesInactiveColor(): void
    {
        $color = $this->em->getRepository(Colors::class)->findOneBy(['name' => 'Negro']);
        $color->deactivate();
        $this->em->flush();

        $service = static::getContainer()->get(ToggleStatusColorsInterface::class);
        $result  = $service->handler($color);

        $this->assertTrue($result['success']);
        $this->em->refresh($color);
        $this->assertTrue($color->isActive());
    }

    public function testListReturnsFixtureColors(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/list'));

        $service = static::getContainer()->get(ListColorsInterface::class);
        $result  = $service->handler();

        $this->assertArrayHasKey('data', $result);
        $this->assertGreaterThanOrEqual(3, count($result['data']));
    }
}
