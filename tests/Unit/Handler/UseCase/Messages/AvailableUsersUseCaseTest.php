<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Messages\AvailableUsersUseCase;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class AvailableUsersUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private UsersRepository&MockObject $usersRepository;
    private GetDomainDataInterface&MockObject $getDomainData;
    private LogInterface&MockObject $log;
    private AvailableUsersUseCase $useCase;

    protected function setUp(): void
    {
        $this->security        = $this->createMock(Security::class);
        $this->usersRepository = $this->createMock(UsersRepository::class);
        $this->getDomainData   = $this->createMock(GetDomainDataInterface::class);
        $this->log             = $this->createMock(LogInterface::class);

        $domain = $this->createMock(Domains::class);
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->useCase = new AvailableUsersUseCase(
            $this->security,
            $this->usersRepository,
            $this->getDomainData,
            $this->log,
        );
    }

    private function makeUser(string $uuidStr, string $name, string $lastName): Users&MockObject
    {
        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn(Uuid::fromString($uuidStr));
        $user->method('getName')->willReturn($name);
        $user->method('getLastName')->willReturn($lastName);
        return $user;
    }

    public function testSuperAdminReceivesMultiModeWithAllUsers(): void
    {
        $currentUser = $this->makeUser('550e8400-e29b-41d4-a716-446655440001', 'Admin', 'Super');
        $this->security->method('getUser')->willReturn($currentUser);
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

        $user1 = $this->makeUser('550e8400-e29b-41d4-a716-446655440002', 'Juan', 'Perez');
        $user2 = $this->makeUser('550e8400-e29b-41d4-a716-446655440003', 'Maria', 'Lopez');
        $this->usersRepository->method('findActiveByDomain')->willReturn([$user1, $user2]);

        $result = $this->useCase->handler();

        $this->assertSame('multi', $result['mode']);
        $this->assertCount(2, $result['users']);
    }

    public function testAdminWithOneSuperAdminReceivesAutoMode(): void
    {
        $currentUser = $this->makeUser('550e8400-e29b-41d4-a716-446655440001', 'Admin', 'Normal');
        $this->security->method('getUser')->willReturn($currentUser);
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $superAdmin = $this->makeUser('550e8400-e29b-41d4-a716-446655440010', 'Super', 'Admin');
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([$superAdmin]);

        $result = $this->useCase->handler();

        $this->assertSame('auto', $result['mode']);
        $this->assertCount(1, $result['users']);
    }

    public function testAdminWithMultipleSuperAdminsReceivesSingleMode(): void
    {
        $currentUser = $this->makeUser('550e8400-e29b-41d4-a716-446655440001', 'Admin', 'Normal');
        $this->security->method('getUser')->willReturn($currentUser);
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $super1 = $this->makeUser('550e8400-e29b-41d4-a716-446655440010', 'Super1', 'Admin');
        $super2 = $this->makeUser('550e8400-e29b-41d4-a716-446655440011', 'Super2', 'Admin');
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([$super1, $super2]);

        $result = $this->useCase->handler();

        $this->assertSame('single', $result['mode']);
        $this->assertCount(2, $result['users']);
    }

    public function testHandlerReturnsAutoEmptyOnException(): void
    {
        $this->security->method('getUser')
            ->willThrowException(new \RuntimeException('Error'));

        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();

        $this->assertSame('auto', $result['mode']);
        $this->assertEmpty($result['users']);
    }
}
