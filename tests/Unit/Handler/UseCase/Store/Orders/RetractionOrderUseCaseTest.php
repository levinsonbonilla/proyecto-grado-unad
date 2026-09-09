<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Orders;

use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Orders\Orders;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Orders\RetractionOrderUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\StatusRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class RetractionOrderUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private StatusRepository&MockObject $statusRepository;
    private CustomeEntityManagerInterface&MockObject $em;
    private LogInterface&MockObject $log;
    private RetractionOrderUseCase $useCase;

    protected function setUp(): void
    {
        $this->security         = $this->createMock(Security::class);
        $this->statusRepository = $this->createMock(StatusRepository::class);
        $this->em               = $this->createMock(CustomeEntityManagerInterface::class);
        $this->log              = $this->createMock(LogInterface::class);

        $this->useCase = new RetractionOrderUseCase(
            $this->security,
            $this->statusRepository,
            $this->em,
            $this->log,
        );
    }

    private function makeUser(): Users&MockObject
    {
        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn(Uuid::v4());
        return $user;
    }

    private function makeOrder(Users $user, string $statusName): Orders&MockObject
    {
        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn($statusName);
        $order = $this->createMock(Orders::class);
        $order->method('getUser')->willReturn($user);
        $order->method('getStatus')->willReturn($status);
        return $order;
    }

    public function testHandlerRequestsRetractionOnDeliveredOrder(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Entregado');
        $this->security->method('getUser')->willReturn($user);

        $retractionStatus = $this->createMock(Status::class);
        $this->statusRepository->method('findOneBy')->willReturn($retractionStatus);
        $order->expects($this->once())->method('edit')->with($retractionStatus);
        $this->em->expects($this->once())->method('add');

        $result = $this->useCase->handler($order);
        $this->assertTrue($result['success']);
    }

    public function testHandlerRefusesRetractionOnPendingOrder(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Pendiente');
        $this->security->method('getUser')->willReturn($user);

        $result = $this->useCase->handler($order);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('entregados', $result['message']);
    }

    public function testHandlerRefusesWhenUserDoesNotOwnOrder(): void
    {
        $owner     = $this->makeUser();
        $requester = $this->makeUser();
        $order     = $this->makeOrder($owner, 'Entregado');
        $this->security->method('getUser')->willReturn($requester);

        $result = $this->useCase->handler($order);
        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsFalseWhenStatusNotFound(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Entregado');
        $this->security->method('getUser')->willReturn($user);
        $this->statusRepository->method('findOneBy')->willReturn(null);

        $result = $this->useCase->handler($order);
        $this->assertFalse($result['success']);
    }
}
