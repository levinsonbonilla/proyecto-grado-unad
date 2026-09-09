<?php

namespace App\Tests\Unit\Handler\UseCase\Dashboard\Users;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Handler\UseCase\Dashboard\Users\EditUserUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class EditUserUseCaseTest extends TestCase
{
    private FormInterface&MockObject $form;
    private FormInterface&MockObject $childForm;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private UsersRepository&MockObject $usersRepository;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private Security&MockObject $security;
    private EditUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->childForm = $this->createMock(FormInterface::class);
        $this->childForm->method('setData')->willReturnSelf();

        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('remove')->willReturnSelf();
        $this->form->method('add')->willReturnSelf();
        $this->form->method('get')->willReturn($this->childForm);
        $this->form->method('getName')->willReturn('users');

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->form);

        $this->request = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log = $this->createMock(LogInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
        $this->usersRepository = $this->createMock(UsersRepository::class);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->useCase = new EditUserUseCase(
            $this->requestStack,
            $formFactory,
            $this->log,
            $this->translator,
            $this->usersRepository,
            $this->entityManager,
            $this->security,
        );
    }

    private function makeDomainForTenant(Tenants&MockObject $tenant): Domains&MockObject
    {
        $domain = $this->createMock(Domains::class);
        $domain->method('getTenant')->willReturn($tenant);
        return $domain;
    }

    private function makeTenant(): Tenants&MockObject
    {
        $tenant = $this->createMock(Tenants::class);
        $tenant->method('getId')->willReturn(Uuid::v4());
        return $tenant;
    }

    private function makeUser(Domains $domain, ?\Symfony\Component\Uid\Uuid $id = null): Users&MockObject
    {
        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn($id ?? Uuid::v4());
        $user->method('getDomain')->willReturn($domain);
        return $user;
    }

    public function testHandlerAllowsSuperAdminToEditUserFromAnyTenant(): void
    {
        $currentUser = $this->makeUser($this->makeDomainForTenant($this->makeTenant()));
        $target      = $this->makeUser($this->makeDomainForTenant($this->makeTenant()));

        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);
        $this->security->method('getUser')->willReturn($currentUser);
        $this->form->method('isSubmitted')->willReturn(false);
        $target->method('getEmail')->willReturn('x@x.com');
        $target->method('getName')->willReturn('X');
        $target->method('getLastName')->willReturn('Y');
        $target->method('isValidatedEmail')->willReturn(true);
        $target->method('isActive')->willReturn(true);
        $target->method('getRoles')->willReturn(['ROLE_USER']);

        $result = $this->useCase->handler($target);
        $this->assertFalse($result->isProcess());
    }

    public function testHandlerRejectsRegularAdminEditingUserFromOtherTenant(): void
    {
        $currentUser = $this->makeUser($this->makeDomainForTenant($this->makeTenant()));
        $target      = $this->makeUser($this->makeDomainForTenant($this->makeTenant()));

        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);
        $this->security->method('getUser')->willReturn($currentUser);

        $this->expectException(AccessDeniedException::class);
        $this->useCase->handler($target);
    }

    public function testHandlerAllowsRegularAdminEditingUserFromSameTenant(): void
    {
        $tenant      = $this->makeTenant();
        $currentUser = $this->makeUser($this->makeDomainForTenant($tenant));
        $target      = $this->makeUser($this->makeDomainForTenant($tenant));

        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);
        $this->security->method('getUser')->willReturn($currentUser);
        $this->form->method('isSubmitted')->willReturn(false);
        $target->method('getEmail')->willReturn('x@x.com');
        $target->method('getName')->willReturn('X');
        $target->method('getLastName')->willReturn('Y');
        $target->method('isValidatedEmail')->willReturn(true);
        $target->method('isActive')->willReturn(true);
        $target->method('getRoles')->willReturn(['ROLE_USER']);

        $result = $this->useCase->handler($target);
        $this->assertFalse($result->isProcess());
    }

    public function testEditChangesRoleAndSyncsUsersDomains(): void
    {
        $domain      = $this->makeDomainForTenant($this->makeTenant());
        $currentUser = $this->makeUser($this->makeDomainForTenant($this->makeTenant()));
        $target      = $this->makeUser($domain);

        $usersDomains = $this->createMock(UsersDomains::class);
        $usersDomains->expects($this->once())->method('rolesChange')->with(['ROLE_ADMIN']);
        $target->method('getUsersDomainsByDomain')->willReturn($usersDomains);
        $target->expects($this->once())->method('rolesChange')->with(['ROLE_ADMIN']);
        $target->method('getRoles')->willReturn(['ROLE_USER']);

        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);
        $this->security->method('getUser')->willReturn($currentUser);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('users', [])->willReturn([
            'email' => 'x@x.com', 'name' => 'X', 'lastName' => 'Y',
            'validatedEmail' => true, 'activated' => true, 'roles' => 'ROLE_ADMIN',
        ]);

        $this->entityManager->expects($this->once())->method('add')->with($target, true);

        $result = $this->useCase->handler($target);
        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testEditRejectsChangingOwnRole(): void
    {
        $id     = Uuid::v4();
        $domain = $this->makeDomainForTenant($this->makeTenant());
        $self   = $this->makeUser($domain, $id);
        $self->method('getRoles')->willReturn(['ROLE_ADMIN']);
        $self->expects($this->never())->method('rolesChange');

        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);
        $this->security->method('getUser')->willReturn($self);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('users', [])->willReturn([
            'email' => 'x@x.com', 'name' => 'X', 'lastName' => 'Y',
            'validatedEmail' => true, 'activated' => true, 'roles' => 'ROLE_SUPER_ADMIN',
        ]);

        $result = $this->useCase->handler($self);
        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testEditRejectsRemovingLastActiveSuperAdmin(): void
    {
        $domain      = $this->makeDomainForTenant($this->makeTenant());
        $currentUser = $this->makeUser($this->makeDomainForTenant($this->makeTenant()));
        $target      = $this->makeUser($domain);
        $target->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN']);
        $target->expects($this->never())->method('rolesChange');

        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);
        $this->security->method('getUser')->willReturn($currentUser);

        $this->usersRepository->method('findActiveSuperAdmins')->willReturn([$target]);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('users', [])->willReturn([
            'email' => 'x@x.com', 'name' => 'X', 'lastName' => 'Y',
            'validatedEmail' => true, 'activated' => true, 'roles' => 'ROLE_ADMIN',
        ]);

        $result = $this->useCase->handler($target);
        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testEditAllowsDemotingSuperAdminWhenAnotherOneRemains(): void
    {
        $domain      = $this->makeDomainForTenant($this->makeTenant());
        $currentUser = $this->makeUser($this->makeDomainForTenant($this->makeTenant()));
        $target      = $this->makeUser($domain);
        $target->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN']);
        $target->expects($this->once())->method('rolesChange')->with(['ROLE_ADMIN']);
        $target->method('getUsersDomainsByDomain')->willReturn(false);

        $anotherSuperAdmin = $this->createMock(Users::class);
        $anotherSuperAdmin->method('getId')->willReturn(Uuid::v4());

        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);
        $this->security->method('getUser')->willReturn($currentUser);
        $this->usersRepository->method('findActiveSuperAdmins')->willReturn([$target, $anotherSuperAdmin]);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('users', [])->willReturn([
            'email' => 'x@x.com', 'name' => 'X', 'lastName' => 'Y',
            'validatedEmail' => true, 'activated' => true, 'roles' => 'ROLE_ADMIN',
        ]);

        $result = $this->useCase->handler($target);
        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }
}
