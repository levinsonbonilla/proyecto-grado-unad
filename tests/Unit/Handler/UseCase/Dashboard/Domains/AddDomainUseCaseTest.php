<?php

namespace App\Tests\Unit\Handler\UseCase\Dashboard\Domains;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Handler\UseCase\Dashboard\Domains\AddDomainUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Tenants\Domains\DomainsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddDomainUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private GetDomainDataInterface&MockObject $getDomainData;
    private DomainsRepository&MockObject $domainsRepository;
    private S3ManagerInterface&MockObject $s3Manager;
    private ParameterBagInterface&MockObject $parameters;
    private CountriesRepository&MockObject $countriesRepository;
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private Security&MockObject $security;
    private Tenants&MockObject $ownTenant;
    private AddDomainUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('domains');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $tmpFile = tempnam(sys_get_temp_dir(), 'logo') . '.png';
        file_put_contents($tmpFile, 'fake-image-content');
        $uploadedFile = new UploadedFile($tmpFile, 'logo.png', 'image/png', null, true);

        $this->request        = $this->createMock(Request::class);
        $this->request->files = new FileBag(['domains' => ['image' => $uploadedFile]]);
        $this->requestStack   = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log           = $this->createMock(LogInterface::class);
        $this->translator    = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);

        $this->ownTenant = $this->createMock(Tenants::class);
        $this->ownTenant->method('getId')->willReturn(Uuid::fromString('11111111-1111-1111-1111-111111111111'));

        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getTenant')->willReturn($this->ownTenant);

        $this->domainsRepository   = $this->createMock(DomainsRepository::class);
        $this->s3Manager           = $this->createMock(S3ManagerInterface::class);
        $this->s3Manager->method('create')->willReturn('https://cdn.example.com/domains/logo.png');
        $this->parameters          = $this->createMock(ParameterBagInterface::class);
        $this->parameters->method('get')->with('upload_domains')->willReturn('/uploads/domains/');
        $this->countriesRepository = $this->createMock(CountriesRepository::class);
        $this->regionsRepository   = $this->createMock(RegionsRepository::class);
        $this->citiesRepository    = $this->createMock(CitiesRepository::class);
        $this->security            = $this->createMock(Security::class);

        $this->useCase = new AddDomainUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
            $this->getDomainData,
            $this->domainsRepository,
            $this->s3Manager,
            $this->parameters,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->security,
        );
    }

    private function baseFormData(): array
    {
        return [
            'domain'            => 'http://nuevo-dominio.test',
            'notificationEmail' => 'noti@example.com',
            'supportEmail'      => 'soporte@example.com',
        ];
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler();

        $this->assertFalse($result->isProcess());
    }

    public function testHandlerAutoProvisionsAccessForRegularAdminOwnTenant(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('domains', [])->willReturn($this->baseFormData());
        $this->domainsRepository->method('findOneBy')->willReturn(null);

        $currentUser = $this->createMock(Users::class);
        $currentUser->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);

        $existingDomain = $this->createMock(Domains::class);
        $existingDomain->method('getTenant')->willReturn($this->ownTenant);
        $existingMembership = $this->createMock(UsersDomains::class);
        $existingMembership->method('getDomain')->willReturn($existingDomain);
        $existingMembership->method('getRoles')->willReturn(['ROLE_ADMIN']);
        $currentUser->method('getUserDomainsActives')->willReturn(new ArrayCollection([$existingMembership]));

        $this->security->method('getUser')->willReturn($currentUser);

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $captured[] = $entity;
        });

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $usersDomains = array_values(array_filter($captured, fn ($e) => $e instanceof UsersDomains))[0] ?? null;
        $this->assertInstanceOf(UsersDomains::class, $usersDomains);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles());
        $this->assertSame($currentUser, $usersDomains->getUser());
    }

    public function testHandlerDoesNotAutoProvisionForSuperAdminOnOtherTenant(): void
    {
        $otherTenant = $this->createMock(Tenants::class);
        $otherTenant->method('getId')->willReturn(Uuid::fromString('22222222-2222-2222-2222-222222222222'));

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('domains', [])->willReturn($this->baseFormData());
        $this->domainsRepository->method('findOneBy')->willReturn(null);

        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $captured[] = $entity;
        });

        $result = $this->useCase->handler($otherTenant);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $usersDomains = array_filter($captured, fn ($e) => $e instanceof UsersDomains);
        $this->assertCount(0, $usersDomains);

        $domain = array_values(array_filter($captured, fn ($e) => $e instanceof Domains))[0] ?? null;
        $this->assertInstanceOf(Domains::class, $domain);
        $this->assertSame($otherTenant, $domain->getTenant());
    }

    public function testHandlerPersistsOptionalName(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('domains', [])
            ->willReturn(array_merge($this->baseFormData(), ['name' => 'Mi Tienda']));
        $this->domainsRepository->method('findOneBy')->willReturn(null);
        $this->security->method('getUser')->willReturn(null);

        $captured = [];
        $this->entityManager->method('add')->willReturnCallback(function (object $entity) use (&$captured) {
            $captured[] = $entity;
        });

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $domain = array_values(array_filter($captured, fn ($e) => $e instanceof Domains))[0] ?? null;
        $this->assertInstanceOf(Domains::class, $domain);
        $this->assertSame('Mi Tienda', $domain->getName());
    }

    public function testHandlerWithExistingDomainReturnsError(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('domains', [])->willReturn($this->baseFormData());

        $this->domainsRepository->method('findOneBy')->willReturn($this->createMock(Domains::class));
        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerWithInvalidFormDoesNotCreateDomain(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertNull($result->getMessage());
    }
}
