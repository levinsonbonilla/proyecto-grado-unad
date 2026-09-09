<?php

namespace App\Tests\Unit\Service\Products;

use App\Entity\Products\Colors\Colors;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Products\Colors\ColorsRepository;
use App\Service\Products\ColorResolverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ColorResolverServiceTest extends TestCase
{
    private ColorsRepository&MockObject $colorsRepository;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private Domains&MockObject $domain;
    private ColorResolverService $service;

    protected function setUp(): void
    {
        $this->colorsRepository = $this->createMock(ColorsRepository::class);
        $this->entityManager    = $this->createMock(CustomeEntityManagerInterface::class);
        $this->domain            = $this->createMock(Domains::class);

        $this->service = new ColorResolverService($this->colorsRepository, $this->entityManager);
    }

    public function testResolveReturnsExistingColorWhenNameMatches(): void
    {
        $existing = $this->createMock(Colors::class);
        $this->colorsRepository->method('findOneByDomainAndName')
            ->with($this->domain, 'Rojo')
            ->willReturn($existing);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->service->resolve($this->domain, 'Rojo', '#ff0000');

        $this->assertSame($existing, $result);
    }

    public function testResolveCreatesNewColorWhenNoneMatches(): void
    {
        $this->colorsRepository->method('findOneByDomainAndName')->willReturn(null);

        $this->entityManager->expects($this->once())->method('add')
            ->with($this->isInstanceOf(Colors::class), true);

        $result = $this->service->resolve($this->domain, 'Verde', '#00ff00');

        $this->assertInstanceOf(Colors::class, $result);
        $this->assertSame('Verde', $result->getName());
        $this->assertSame('#00ff00', $result->getHexCode());
    }

    public function testResolveCreatesNewColorWithDefaultHexWhenNoneProvided(): void
    {
        $this->colorsRepository->method('findOneByDomainAndName')->willReturn(null);

        $result = $this->service->resolve($this->domain, 'Sin Hex', null);

        $this->assertSame('#6775d6', $result->getHexCode());
    }

    public function testResolveCreatesNewColorWithDefaultHexWhenEmptyStringProvided(): void
    {
        $this->colorsRepository->method('findOneByDomainAndName')->willReturn(null);

        $result = $this->service->resolve($this->domain, 'Hex Vacío', '');

        $this->assertSame('#6775d6', $result->getHexCode());
    }
}
