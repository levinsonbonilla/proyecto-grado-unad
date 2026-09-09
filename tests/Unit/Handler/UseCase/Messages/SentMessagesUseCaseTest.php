<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Handler\UseCase\Messages\SentMessagesUseCase;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessagesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class SentMessagesUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private HelpMessagesRepository&MockObject $repository;
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private SentMessagesUseCase $useCase;

    protected function setUp(): void
    {
        $this->security      = $this->createMock(Security::class);
        $this->repository    = $this->createMock(HelpMessagesRepository::class);
        $this->listDataTable = $this->createMock(ListDataTableInterface::class);
        $this->log           = $this->createMock(LogInterface::class);

        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);

        $this->useCase = new SentMessagesUseCase(
            $this->security,
            $this->repository,
            $this->listDataTable,
            $this->log,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {
        $this->repository->method('getSent')
            ->willReturnOnConsecutiveCalls(
                [['total' => '1']],
                [['id' => 'uuid-1', 'message' => 'Enviado', 'isRead' => true]]
            );

        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->repository->method('getSent')
            ->willThrowException(new \RuntimeException('Error'));

        $this->log->method('handler')->willReturn(null);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('data', $result);
    }
}
