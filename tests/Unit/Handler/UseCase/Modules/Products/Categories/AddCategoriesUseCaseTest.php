<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Categories;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Form\Modules\Products\CategoriesType;
use App\Handler\UseCase\Modules\Products\Categories\AddCategoriesUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Products\Categories\CategoriesRepository;
use App\ReturnHandler\FormReturn;
use App\Util\ImageUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddCategoriesUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private GetDomainDataInterface&MockObject $getDomainData;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private ImageUtil $imageUtil;
    private ParameterBagInterface&MockObject $parameters;
    private Security&MockObject $security;
    private CountriesRepository&MockObject $countriesRepository;
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private CategoriesRepository&MockObject $categoriesRepository;
    private AddCategoriesUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('categories');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request        = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);
        $this->requestStack   = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log                 = $this->createMock(LogInterface::class);
        $this->translator          = $this->createMock(TranslatorInterface::class);
        $this->entityManager       = $this->createMock(CustomeEntityManagerInterface::class);

        $this->imageUtil           = new ImageUtil($this->createMock(S3ManagerInterface::class));
        $this->parameters          = $this->createMock(ParameterBagInterface::class);
        $this->security             = $this->createMock(Security::class);
        $this->countriesRepository = $this->createMock(CountriesRepository::class);
        $this->regionsRepository   = $this->createMock(RegionsRepository::class);
        $this->citiesRepository    = $this->createMock(CitiesRepository::class);
        $this->categoriesRepository = $this->createMock(CategoriesRepository::class);

        $domain = $this->createMock(Domains::class);
        $tenant = $this->createMock(Tenants::class);
        $tenant->method('getId')->willReturn(Uuid::fromString('11111111-1111-1111-1111-111111111111'));

        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomain')->willReturn($domain);
        $this->getDomainData->method('getTenantCache')->willReturn($tenant);

        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn(Uuid::fromString('22222222-2222-2222-2222-222222222222'));
        $this->security->method('getUser')->willReturn($user);

        $this->parameters->method('get')->with('upload_products')->willReturn('/uploads/products/');

        $this->useCase = new AddCategoriesUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->getDomainData,
            $this->entityManager,
            $this->imageUtil,
            $this->parameters,
            $this->security,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->categoriesRepository,
        );
    }

    private function baseFormData(): array
    {
        return ['name' => 'Electrónica', 'description' => 'Categoría de electrónica'];
    }

    public function testHandlerReturnsFormReturnInstance(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler(CategoriesType::class);

        $this->assertInstanceOf(FormReturn::class, $result);
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler(CategoriesType::class);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNull($result->getMessage());
    }

    public function testHandlerWithValidFormCreatesCategoryWithoutGeoSelection(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('categories', [])->willReturn($this->baseFormData());

        $this->countriesRepository->expects($this->never())->method('find');
        $this->regionsRepository->expects($this->never())->method('find');
        $this->citiesRepository->expects($this->never())->method('find');

        $this->entityManager->expects($this->once())->method('add');
        $this->entityManager->expects($this->once())->method('flush');
        $this->categoriesRepository->expects($this->once())->method('invalidatePublicCache');
        $this->translator->method('trans')->willReturn('Categoría creada');

        $result = $this->useCase->handler(CategoriesType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithValidFormCreatesCategoryWithGeoSelection(): void
    {
        $countryId = '11111111-1111-1111-1111-111111111111';
        $regionId  = '22222222-2222-2222-2222-222222222222';
        $cityId    = '33333333-3333-3333-3333-333333333333';
        $data      = $this->baseFormData();
        $data['countries'] = [$countryId];
        $data['regions']   = [$regionId];
        $data['cities']    = [$cityId];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('categories', [])->willReturn($data);

        $this->countriesRepository->expects($this->once())->method('find')->with($countryId)
            ->willReturn($this->createMock(Countries::class));
        $this->regionsRepository->expects($this->once())->method('find')->with($regionId)
            ->willReturn($this->createMock(Regions::class));
        $this->citiesRepository->expects($this->once())->method('find')->with($cityId)
            ->willReturn($this->createMock(Cities::class));

        $this->entityManager->expects($this->exactly(4))->method('add');
        $this->translator->method('trans')->willReturn('Categoría creada');

        $result = $this->useCase->handler(CategoriesType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithSubmittedInvalidFormDoesNotPersist(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler(CategoriesType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCatchesUnexpectedException(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('categories', [])->willReturn($this->baseFormData());

        $this->entityManager->method('add')
            ->willThrowException(new \RuntimeException('Error de base de datos'));

        $this->log->method('handler')->willReturn(null);
        $this->translator->method('trans')->willReturn('Error de registro');

        $result = $this->useCase->handler(CategoriesType::class);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
