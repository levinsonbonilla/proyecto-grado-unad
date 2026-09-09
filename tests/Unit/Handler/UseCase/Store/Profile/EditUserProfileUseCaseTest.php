<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Profile;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Profile\EditUserProfileUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class EditUserProfileUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private CountriesRepository&MockObject $countriesRepository;
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private CustomeEntityManagerInterface&MockObject $em;
    private LogInterface&MockObject $log;
    private EditUserProfileUseCase $useCase;

    protected function setUp(): void
    {
        $this->security            = $this->createMock(Security::class);
        $this->countriesRepository = $this->createMock(CountriesRepository::class);
        $this->regionsRepository   = $this->createMock(RegionsRepository::class);
        $this->citiesRepository    = $this->createMock(CitiesRepository::class);
        $this->em  = $this->createMock(CustomeEntityManagerInterface::class);
        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new EditUserProfileUseCase(
            $this->security,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->em,
            $this->log,
        );
    }

    private function makeAuthenticatedUser(): Users&MockObject
    {
        $domain = $this->createMock(Domains::class);
        $user   = $this->createMock(Users::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('isValidatedEmail')->willReturn(true);
        $user->method('getDomain')->willReturn($domain);
        return $user;
    }

    public function testHandlerReturnsFalseWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $result = $this->useCase->handler(['name' => 'A', 'lastName' => 'B']);
        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsFalseWithMissingName(): void
    {
        $this->security->method('getUser')->willReturn($this->makeAuthenticatedUser());

        $result = $this->useCase->handler(['name' => '', 'lastName' => 'García']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('obligatorio', $result['message']);
    }

    public function testHandlerReturnsFalseWithMissingLastName(): void
    {
        $this->security->method('getUser')->willReturn($this->makeAuthenticatedUser());

        $result = $this->useCase->handler(['name' => 'Carlos', 'lastName' => '']);
        $this->assertFalse($result['success']);
    }

    public function testHandlerUpdatesProfileSuccessfully(): void
    {
        $user = $this->makeAuthenticatedUser();
        $user->expects($this->once())->method('edit');
        $this->em->expects($this->once())->method('add');
        $this->security->method('getUser')->willReturn($user);

        $result = $this->useCase->handler([
            'name'     => 'Carlos',
            'lastName' => 'García',
            'phone'    => '+57 300 000 0000',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testHandlerReturnsFalseOnException(): void
    {
        $user = $this->makeAuthenticatedUser();
        $user->method('edit')->willThrowException(new \RuntimeException('DB error'));
        $this->log->expects($this->once())->method('handler');
        $this->security->method('getUser')->willReturn($user);

        $result = $this->useCase->handler(['name' => 'Carlos', 'lastName' => 'García']);
        $this->assertFalse($result['success']);
    }
}
