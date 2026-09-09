<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Profile;

use App\Entity\Configurations\Globals\Countries;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Profile\GetUserProfileUseCase;
use App\Interface\UseCase\Security\LogInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GetUserProfileUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private RequestStack&MockObject $requestStack;
    private LogInterface&MockObject $log;
    private GetUserProfileUseCase $useCase;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);

        $request = $this->createMock(Request::class);
        $request->method('getLocale')->willReturn('es');
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new GetUserProfileUseCase(
            $this->security,
            $this->requestStack,
            $this->log,
        );
    }

    public function testHandlerReturnsEmptyArrayWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $result = $this->useCase->handler();
        $this->assertSame([], $result);
    }

    public function testHandlerReturnsProfileDataForAuthenticatedUser(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Carlos');
        $user->method('getLastName')->willReturn('García');
        $user->method('getEmail')->willReturn('carlos@example.com');
        $user->method('getPhone')->willReturn('+57 300 000 0000');
        $user->method('getAddress')->willReturn('Calle 1 # 2-3');
        $user->method('getNeighborhood')->willReturn('Centro');
        $user->method('getPrefix')->willReturn('+57');
        $user->method('getDateOfBirth')->willReturn(new \DateTimeImmutable('1990-05-15'));
        $user->method('getCountry')->willReturn(null);
        $user->method('getRegion')->willReturn(null);
        $user->method('getCity')->willReturn(null);
        $user->method('getProfilePicture')->willReturn(null);
        $user->method('getPoints')->willReturn(null);

        $this->security->method('getUser')->willReturn($user);

        $result = $this->useCase->handler();

        $this->assertSame('Carlos', $result['name']);
        $this->assertSame('García', $result['lastName']);
        $this->assertSame('carlos@example.com', $result['email']);
        $this->assertSame('1990-05-15', $result['dateOfBirth']);
        $this->assertArrayHasKey('countryId', $result);
        $this->assertNull($result['countryId']);
    }

    public function testHandlerUsesRequestLocaleForCountryName(): void
    {
        $country = $this->createMock(Countries::class);
        $country->expects($this->once())->method('getName')->with('es')->willReturn('Colombia');

        $user = $this->createMock(Users::class);
        $user->method('getCountry')->willReturn($country);
        $user->method('getRegion')->willReturn(null);
        $user->method('getCity')->willReturn(null);
        $this->security->method('getUser')->willReturn($user);

        $result = $this->useCase->handler();

        $this->assertSame('Colombia', $result['countryName']);
    }

    public function testHandlerReturnsEmptyOnException(): void
    {
        $this->security->method('getUser')->willThrowException(new \RuntimeException('Auth error'));
        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();
        $this->assertSame([], $result);
    }
}
