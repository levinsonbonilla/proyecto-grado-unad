<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Handler\UseCase\Messages\InboxMessagesUseCase;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessagesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class InboxMessagesUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private HelpMessagesRepository&MockObject $repository;
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private RequestStack&MockObject $requestStack;
    private InboxMessagesUseCase $useCase;

    protected function setUp(): void
    {
        $this->security      = $this->createMock(Security::class);
        $this->repository    = $this->createMock(HelpMessagesRepository::class);
        $this->listDataTable = $this->createMock(ListDataTableInterface::class);
        $this->log           = $this->createMock(LogInterface::class);
        $this->requestStack  = $this->createMock(RequestStack::class);

        $request = $this->createMock(Request::class);
        $request->method('get')->willReturn(null);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);

        $this->useCase = new InboxMessagesUseCase(
            $this->security,
            $this->repository,
            $this->listDataTable,
            $this->log,
            $this->requestStack,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {
        $this->repository->method('getInbox')
            ->willReturnOnConsecutiveCalls(
                [['total' => '2']],
                [['id' => 'uuid-1', 'message' => 'Hola', 'isRead' => false]]
            );

        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testHandlerReturnsDataFromRepository(): void
    {
        $rows = [
            ['id' => 'uuid-1', 'message' => 'Test', 'isRead' => false],
        ];

        $this->repository->method('getInbox')
            ->willReturnOnConsecutiveCalls(
                [['total' => '1']],
                $rows
            );

        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertSame($rows, $result['data']);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->repository->method('getInbox')
            ->willThrowException(new \RuntimeException('Error'));

        $this->log->method('handler')->willReturn(null);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('data', $result);
    }
}
