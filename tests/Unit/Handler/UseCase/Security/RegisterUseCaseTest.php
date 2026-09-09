<?php

namespace App\Tests\Unit\Handler\UseCase\Security;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Handler\UseCase\Security\RegisterUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\LocationInterface;
use App\Interface\Configuration\MailerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersDomainsRepository;
use App\Repository\Users\UsersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private TranslatorInterface&MockObject $translator;
    private GetDomainDataInterface&MockObject $getDomainData;
    private LocationInterface&MockObject $location;
    private UsersRepository&MockObject $usersRepository;
    private UsersDomainsRepository&MockObject $usersDomainsRepository;
    private UserPasswordHasherInterface&MockObject $userPasswordHasher;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private MailerInterface&MockObject $mailer;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private LogInterface&MockObject $log;
    private Domains&MockObject $domain;
    private RegisterUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $request = Request::create('http://localhost:8060/es/register');
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->domain = $this->createMock(Domains::class);
        $this->domain->method('getSupportEmail')->willReturn('support@example.com');
        $tenant = $this->createMock(\App\Entity\Tenants\Tenants::class);
        $tenant->method('getName')->willReturn('Tenant de prueba');
        $tenant->method('isPrincipal')->willReturn(false);
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomain')->willReturn($this->domain);
        $this->getDomainData->method('getDomainCache')->willReturn($this->domain);
        $this->getDomainData->method('getTenantCache')->willReturn($tenant);

        $this->location = $this->createMock(LocationInterface::class);

        $this->usersRepository = $this->createMock(UsersRepository::class);
        $this->usersDomainsRepository = $this->createMock(UsersDomainsRepository::class);
        $this->userPasswordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userPasswordHasher->method('hashPassword')->willReturn('hashed');
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturn('/es/login/confirmation/abc');
        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new RegisterUseCase(
            $this->formFactory,
            $this->usersRepository,
            $this->log,
            $this->requestStack,
            $this->translator,
            $this->getDomainData,
            $this->location,
            $this->mailer,
            $this->userPasswordHasher,
            $this->urlGenerator,
            $this->usersDomainsRepository,
            $this->entityManager,
        );
    }

    private function assignFakeId(object $entity): void
    {
        if (!$entity instanceof Users) {
            return;
        }
        $property = new \ReflectionProperty(Users::class, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, Uuid::v4());
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler([]);

        $this->assertFalse($result->isProcess());
    }

    public function testHandlerWithInvalidSubmissionDoesNotCrashAndReturnsError(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler([]);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerCreatesUserDomainRoleUserExplicitly(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->usersRepository->method('findOneBy')->willReturn(null);

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $this->assignFakeId($entity);
            $captured[] = $entity;
        });

        $data = [
            'email' => 'cliente@example.com',
            'password' => 'ClaveSegura123',
            'name' => 'Cliente',
            'lastName' => 'Prueba',
        ];

        $result = $this->useCase->handler($data);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $usersDomains = array_values(array_filter($captured, fn($e) => $e instanceof UsersDomains))[0] ?? null;
        $this->assertInstanceOf(UsersDomains::class, $usersDomains);
        $this->assertSame(['ROLE_USER'], $usersDomains->getRoles());
    }

    public function testHandlerOnPrincipalDomainCreatesTenantDomainAndAdminUser(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->usersRepository->method('findOneBy')->willReturn(null);

        $principalTenant = $this->createMock(Tenants::class);
        $principalTenant->method('isPrincipal')->willReturn(true);
        $principalTenant->method('getName')->willReturn('proyecto-grado-unad');
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomain')->willReturn($this->domain);
        $this->getDomainData->method('getDomainCache')->willReturn($this->domain);
        $this->getDomainData->method('getTenantCache')->willReturn($principalTenant);

        $this->useCase = new RegisterUseCase(
            $this->formFactory,
            $this->usersRepository,
            $this->log,
            $this->requestStack,
            $this->translator,
            $this->getDomainData,
            $this->location,
            $this->mailer,
            $this->userPasswordHasher,
            $this->urlGenerator,
            $this->usersDomainsRepository,
            $this->entityManager,
        );

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $this->assignFakeId($entity);
            $captured[] = $entity;
        });

        $data = [
            'businessName' => 'Acme Inc',
            'phone' => '3005551234',
            'email' => 'nuevo.negocio@example.com',
            'password' => 'ClaveSegura123',
            'name' => 'Dueño',
            'lastName' => 'Negocio',
        ];

        $result = $this->useCase->handler($data);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $tenant = array_values(array_filter($captured, fn($e) => $e instanceof Tenants))[0] ?? null;
        $this->assertInstanceOf(Tenants::class, $tenant);
        $this->assertSame('Acme Inc', $tenant->getName());
        $this->assertSame('0', $tenant->getNit());
        $this->assertSame('3005551234', $tenant->getPhone());

        $domain = array_values(array_filter($captured, fn($e) => $e instanceof Domains))[0] ?? null;
        $this->assertInstanceOf(Domains::class, $domain);
        $this->assertSame('new_tenant_domain_placeholder', $domain->getDomain());
        $this->assertSame('nuevo.negocio@example.com', $domain->getNotificationEmail());
        $this->assertSame('nuevo.negocio@example.com', $domain->getSupportEmail());

        $usersDomains = array_values(array_filter($captured, fn($e) => $e instanceof UsersDomains))[0] ?? null;
        $this->assertInstanceOf(UsersDomains::class, $usersDomains);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles());
    }

    public function testHandlerOnPrincipalDomainIgnoresRolesFromPayload(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->usersRepository->method('findOneBy')->willReturn(null);

        $principalTenant = $this->createMock(Tenants::class);
        $principalTenant->method('isPrincipal')->willReturn(true);
        $principalTenant->method('getName')->willReturn('proyecto-grado-unad');
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomain')->willReturn($this->domain);
        $this->getDomainData->method('getDomainCache')->willReturn($this->domain);
        $this->getDomainData->method('getTenantCache')->willReturn($principalTenant);

        $this->useCase = new RegisterUseCase(
            $this->formFactory,
            $this->usersRepository,
            $this->log,
            $this->requestStack,
            $this->translator,
            $this->getDomainData,
            $this->location,
            $this->mailer,
            $this->userPasswordHasher,
            $this->urlGenerator,
            $this->usersDomainsRepository,
            $this->entityManager,
        );

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $this->assignFakeId($entity);
            $captured[] = $entity;
        });

        $data = [
            'businessName' => 'Acme Inc',
            'phone' => '3005551234',
            'email' => 'atacante@example.com',
            'password' => 'ClaveSegura123',
            'name' => 'Dueño',
            'lastName' => 'Negocio',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ];

        $this->useCase->handler($data);

        $usersDomains = array_values(array_filter($captured, fn($e) => $e instanceof UsersDomains))[0] ?? null;
        $this->assertInstanceOf(UsersDomains::class, $usersDomains);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles());
    }
}
