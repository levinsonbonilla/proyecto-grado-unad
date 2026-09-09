<?php

namespace App\Tests\Unit\Service\Products;

use App\Entity\Products\Medidas\Medidas;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Products\Medidas\MedidasRepository;
use App\Service\Products\MedidaResolverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MedidaResolverServiceTest extends TestCase
{
    private MedidasRepository&MockObject $medidasRepository;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private Domains&MockObject $domain;
    private MedidaResolverService $service;

    protected function setUp(): void
    {
        $this->medidasRepository = $this->createMock(MedidasRepository::class);
        $this->entityManager     = $this->createMock(CustomeEntityManagerInterface::class);
        $this->domain             = $this->createMock(Domains::class);

        $this->service = new MedidaResolverService($this->medidasRepository, $this->entityManager);
    }

    public function testResolveReturnsExistingMedidaWhenNameMatches(): void
    {
        $existing = $this->createMock(Medidas::class);
        $this->medidasRepository->method('findOneByDomainAndName')
            ->with($this->domain, 'M')
            ->willReturn($existing);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->service->resolve($this->domain, 'M');

        $this->assertSame($existing, $result);
    }

    public function testResolveCreatesNewMedidaWhenNoneMatches(): void
    {
        $this->medidasRepository->method('findOneByDomainAndName')->willReturn(null);

        $this->entityManager->expects($this->once())->method('add')
            ->with($this->isInstanceOf(Medidas::class), true);

        $result = $this->service->resolve($this->domain, 'XL');

        $this->assertInstanceOf(Medidas::class, $result);
        $this->assertSame('XL', $result->getName());
    }
}
