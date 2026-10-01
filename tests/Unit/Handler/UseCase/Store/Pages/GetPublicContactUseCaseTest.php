<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Pages;

use App\Entity\Tenants\Domains\Domains;
use App\Exception\GenericException;
use App\Handler\UseCase\Store\Pages\GetPublicContactUseCase;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetPublicContactUseCaseTest extends TestCase
{
    private GetDomainDataInterface&MockObject $getDomainData;
    private LogInterface&MockObject $log;
    private GetPublicContactUseCase $useCase;

    protected function setUp(): void
    {
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->log = $this->createMock(LogInterface::class);
        $this->useCase = new GetPublicContactUseCase($this->getDomainData, $this->log);
    }

    public function testReturnsContactDataFromCurrentDomain(): void
    {
        $domain = (new Domains())->changeContact('Calle 1', '123', 'a@b.com');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->assertSame(
            ['address' => 'Calle 1', 'phone' => '123', 'email' => 'a@b.com'],
            $this->useCase->handler()
        );
    }

    public function testReturnsNullsWhenDomainHasNoContactData(): void
    {
        $this->getDomainData->method('getDomainCache')->willReturn(new Domains());

        $this->assertSame(
            ['address' => null, 'phone' => null, 'email' => null],
            $this->useCase->handler()
        );
    }

    public function testLogsAndReturnsNullsWhenDomainCannotBeResolved(): void
    {
        $this->getDomainData->method('getDomainCache')->willThrowException(new GenericException('x', 404));
        $this->log->expects($this->once())->method('handler');

        $this->assertSame(
            ['address' => null, 'phone' => null, 'email' => null],
            $this->useCase->handler()
        );
    }
}
