<?php

namespace App\Tests\Unit\Handler\UseCase\Security;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Handler\UseCase\Security\RegisterTenantUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\MailerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Users\UsersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterTenantUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private TranslatorInterface&MockObject $translator;
    private UsersRepository&MockObject $usersRepository;
    private DomainsRepository&MockObject $domainsRepository;
    private UserPasswordHasherInterface&MockObject $userPasswordHasher;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private MailerInterface&MockObject $mailer;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private LogInterface&MockObject $log;
    private RegisterTenantUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->usersRepository = $this->createMock(UsersRepository::class);
        $this->domainsRepository = $this->createMock(DomainsRepository::class);
        $this->userPasswordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userPasswordHasher->method('hashPassword')->willReturn('hashed');
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturn('/es/login/confirmation/abc');
        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new RegisterTenantUseCase(
            $this->formFactory,
            $this->requestStack,
            $this->translator,
            $this->usersRepository,
            $this->domainsRepository,
            $this->userPasswordHasher,
            $this->entityManager,
            $this->mailer,
            $this->urlGenerator,
            $this->log,
            'proyecto-grado-unad.app',
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

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'businessName' => 'Mi Negocio',
            'slug' => 'mi-negocio',
            'email' => 'dueno@example.com',
            'name' => 'Dueno',
            'lastName' => 'Prueba',
            'password' => 'SuperClave123',
        ], $overrides);
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler([]);

        $this->assertFalse($result->isProcess());
        $this->assertNull($result->getMessage());
    }

    public function testHandlerCreatesTenantDomainUserAndHardcodesRoleAdmin(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->domainsRepository->method('findOneBy')->willReturn(null);
        $this->usersRepository->method('findOneBy')->willReturn(null);

        $captured = [];
        $this->entityManager->expects($this->exactly(4))
            ->method('add')
            ->willReturnCallback(function (object $entity) use (&$captured) {
                $this->assignFakeId($entity);
                $captured[] = $entity;
            });

        $result = $this->useCase->handler($this->validData());

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $usersDomains = array_values(array_filter($captured, fn($e) => $e instanceof UsersDomains))[0] ?? null;
        $this->assertInstanceOf(UsersDomains::class, $usersDomains);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles());

        $domain = array_values(array_filter($captured, fn($e) => $e instanceof Domains))[0] ?? null;
        $this->assertInstanceOf(Domains::class, $domain);
        $this->assertSame('https://mi-negocio.proyecto-grado-unad.app', $domain->getDomain());

        $this->assertNotEmpty(array_filter($captured, fn($e) => $e instanceof Tenants));
        $this->assertNotEmpty(array_filter($captured, fn($e) => $e instanceof Users));
    }

    public function testHandlerIgnoresRolesFromPayload(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->domainsRepository->method('findOneBy')->willReturn(null);
        $this->usersRepository->method('findOneBy')->willReturn(null);

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $captured[] = $entity;
        });

        $this->useCase->handler($this->validData(['roles' => ['ROLE_SUPER_ADMIN']]));

        $usersDomains = array_values(array_filter($captured, fn($e) => $e instanceof UsersDomains))[0] ?? null;
        $this->assertInstanceOf(UsersDomains::class, $usersDomains);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles());
    }

    public function testHandlerReusesExistingUserByEmail(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->domainsRepository->method('findOneBy')->willReturn(null);

        $existingUser = $this->createMock(Users::class);
        $existingUser->method('isValidatedEmail')->willReturn(true);
        $this->usersRepository->method('findOneBy')->willReturn($existingUser);

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $captured[] = $entity;
        });

        $result = $this->useCase->handler($this->validData());

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertEmpty(array_filter($captured, fn($e) => $e instanceof Users), 'no debe crear un Users nuevo si el email ya existe');

        $usersDomains = array_values(array_filter($captured, fn($e) => $e instanceof UsersDomains))[0] ?? null;
        $this->assertSame($existingUser, $usersDomains->getUser());
    }

    public function testHandlerRejectsReservedSlug(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler($this->validData(['slug' => 'admin']));

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerRejectsInvalidSlugFormat(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler($this->validData(['slug' => 'Not Valid!!']));

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerRejectsTakenSlug(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->domainsRepository->method('findOneBy')->willReturn($this->createMock(Domains::class));

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler($this->validData());

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerSendsVerificationEmailForUnvalidatedUser(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->domainsRepository->method('findOneBy')->willReturn(null);
        $this->usersRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('add')->willReturnCallback(fn(object $entity) => $this->assignFakeId($entity));

        $this->mailer->expects($this->once())->method('sendEmailGeneric');

        $result = $this->useCase->handler($this->validData());

        $this->assertFalse($result->isError());
    }
}
