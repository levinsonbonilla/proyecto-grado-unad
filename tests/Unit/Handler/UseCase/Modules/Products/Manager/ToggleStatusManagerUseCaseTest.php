<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Manager;

use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Handler\UseCase\Modules\Products\Manager\ToggleStatusManagerUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\ProductsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ToggleStatusManagerUseCaseTest extends TestCase
{
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private ProductsRepository&MockObject $productsRepository;
    private LogInterface&MockObject $log;
    private Products&MockObject $entity;
    private ToggleStatusManagerUseCase $useCase;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->productsRepository = $this->createMock(ProductsRepository::class);
        $this->log = $this->createMock(LogInterface::class);
        $this->entity = $this->createMock(Products::class);
        $this->entity->method('getDomain')->willReturn($this->createMock(Domains::class));

        $this->useCase = new ToggleStatusManagerUseCase(
            $this->entityManager,
            $this->productsRepository,
            $this->log,
        );
    }

    public function testHandlerDeactivatesActiveEntity(): void
    {
        $this->entity->method('isActive')->willReturn(true);
        $this->entity->expects($this->once())->method('deactivate');
        $this->entity->expects($this->never())->method('activate');

        $this->entityManager->expects($this->once())->method('add');
        $this->productsRepository->expects($this->once())->method('invalidatePublicHomeCache');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result['success']);
    }

    public function testHandlerActivatesInactiveEntity(): void
    {
        $this->entity->method('isActive')->willReturn(false);
        $this->entity->expects($this->once())->method('activate');
        $this->entity->expects($this->never())->method('deactivate');

        $this->entityManager->expects($this->once())->method('add');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result['success']);
    }

    public function testHandlerReturnsFalseOnException(): void
    {
        $this->entity->method('isActive')->willReturn(true);
        $this->entity->method('deactivate')
            ->willThrowException(new \RuntimeException('Error de persistencia'));

        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsSuccessArrayStructure(): void
    {
        $this->entity->method('isActive')->willReturn(true);

        $result = $this->useCase->handler($this->entity);

        $this->assertArrayHasKey('success', $result);
    }
}
