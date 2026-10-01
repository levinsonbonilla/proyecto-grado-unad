<?php

namespace App\Tests\Unit\Handler\UseCase\Dashboard\AboutSections;

use App\ArgumentHandler\AboutSectionsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Others\AboutSections;
use App\Handler\UseCase\Dashboard\AboutSections\ToggleStatusAboutSectionUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\AboutSectionsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ToggleStatusAboutSectionUseCaseTest extends TestCase
{
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private AboutSectionsRepository&MockObject $repository;
    private LogInterface&MockObject $log;
    private ToggleStatusAboutSectionUseCase $useCase;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->repository = $this->createMock(AboutSectionsRepository::class);
        $this->log = $this->createMock(LogInterface::class);
        $this->useCase = new ToggleStatusAboutSectionUseCase($this->entityManager, $this->repository, $this->log);
    }

    private function buildSection(): AboutSections
    {
        return (new AboutSections())->add(
            new AboutSectionsArgument(['title' => 'A', 'text' => 'B'], new Domains())
        );
    }

    public function testDeactivatesActiveSectionAndInvalidatesCache(): void
    {
        $section = $this->buildSection();
        $this->entityManager->expects($this->once())->method('add')->with($section, true);
        $this->repository->expects($this->once())->method('invalidatePublicCache')->with($section->getDomain());

        $result = $this->useCase->handler($section);

        $this->assertSame(['success' => true], $result);
        $this->assertFalse($section->isActive());
    }

    public function testActivatesInactiveSection(): void
    {
        $section = $this->buildSection();
        $section->deactivate();

        $result = $this->useCase->handler($section);

        $this->assertSame(['success' => true], $result);
        $this->assertTrue($section->isActive());
    }

    public function testLogsAndReturnsFailureWhenPersistenceFails(): void
    {
        $section = $this->buildSection();
        $this->entityManager->method('add')->willThrowException(new \RuntimeException('db'));
        $this->log->expects($this->once())->method('handler');

        $this->assertSame(['success' => false], $this->useCase->handler($section));
    }
}
