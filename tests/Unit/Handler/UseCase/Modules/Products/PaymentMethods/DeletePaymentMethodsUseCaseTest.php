<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Tenants\Domains\PaymentMethods;
use App\Handler\UseCase\Modules\Products\PaymentMethods\DeletePaymentMethodsUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeletePaymentMethodsUseCaseTest extends TestCase
{
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private LogInterface&MockObject $log;
    private PaymentMethods&MockObject $entity;
    private DeletePaymentMethodsUseCase $useCase;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->log           = $this->createMock(LogInterface::class);
        $this->entity        = $this->createMock(PaymentMethods::class);

        $this->useCase = new DeletePaymentMethodsUseCase(
            $this->entityManager,
            $this->log,
        );
    }

    public function testHandlerDeactivatesActiveEntity(): void
    {
        $this->entity->method('isActive')->willReturn(true);
        $this->entity->expects($this->once())->method('deactivate');
        $this->entity->expects($this->never())->method('activate');

        $this->entityManager->expects($this->once())->method('add');

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
