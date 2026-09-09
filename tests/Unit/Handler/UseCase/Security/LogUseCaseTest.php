<?php

namespace App\Tests\Unit\Handler\UseCase\Security;

use App\Entity\Configurations\Globals\Logs;
use App\Handler\UseCase\Security\LogUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Configurations\Globals\LogsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class LogUseCaseTest extends TestCase
{
    private LogsRepository&MockObject $logsRepository;
    private KernelInterface&MockObject $kernel;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private LogUseCase $useCase;

    protected function setUp(): void
    {
        $this->logsRepository = $this->createMock(LogsRepository::class);
        $this->kernel         = $this->createMock(KernelInterface::class);
        $this->entityManager  = $this->createMock(CustomeEntityManagerInterface::class);
        $this->logger         = $this->createMock(LoggerInterface::class);

        $this->useCase = new LogUseCase(
            $this->logsRepository,
            $this->kernel,
            $this->entityManager,
            $this->logger
        );
    }

    public function testHandlerReturnsPersistedLogOnSuccess(): void
    {
        $this->entityManager->expects($this->once())->method('add');
        $this->logger->expects($this->never())->method('error');

        $log = $this->useCase->handler(new \RuntimeException('Boom'));

        $this->assertInstanceOf(Logs::class, $log);
        $this->assertNotEmpty((string) $log->getId());
    }

    public function testHandlerReturnsFallbackLogWithRealReferenceWhenPersistFails(): void
    {

        $this->entityManager->method('add')->willThrowException(new \RuntimeException('EntityManager is closed'));
        $this->logger->expects($this->once())->method('error');

        $log = $this->useCase->handler(new \RuntimeException('Original failure'));

        $this->assertInstanceOf(Logs::class, $log);
        $this->assertNotEmpty($log->getShortReference());
    }
}
