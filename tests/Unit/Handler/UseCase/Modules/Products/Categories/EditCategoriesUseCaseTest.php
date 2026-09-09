<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Categories;

use App\Entity\Products\Categories\Categories;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Handler\UseCase\Modules\Products\Categories\EditCategoriesUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Cities\CitiesCategoriesRepository;
use App\Repository\Configurations\Countries\CountriesCategoriesRepository;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Configurations\Regions\RegionsCategoriesRepository;
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

class EditCategoriesUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private FormInterface&MockObject $childForm;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private ImageUtil $imageUtil;
    private ParameterBagInterface&MockObject $parameters;
    private Security&MockObject $security;
    private GetDomainDataInterface&MockObject $getDomainData;
    private CountriesCategoriesRepository&MockObject $countriesCategoriesRepository;
    private RegionsCategoriesRepository&MockObject $regionsCategoriesRepository;
    private CitiesCategoriesRepository&MockObject $citiesCategoriesRepository;
    private CountriesRepository&MockObject $countriesRepository;
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private CategoriesRepository&MockObject $categoriesRepository;
    private Categories&MockObject $entity;
    private EditCategoriesUseCase $useCase;

    protected function setUp(): void
    {
        $this->childForm = $this->createMock(FormInterface::class);
        $this->childForm->method('setData')->willReturnSelf();

        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('add')->willReturnSelf();
        $this->form->method('get')->willReturn($this->childForm);

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request        = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);
        $this->requestStack   = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log                          = $this->createMock(LogInterface::class);
        $this->translator                   = $this->createMock(TranslatorInterface::class);
        $this->entityManager                = $this->createMock(CustomeEntityManagerInterface::class);

        $this->imageUtil                    = new ImageUtil($this->createMock(S3ManagerInterface::class));
        $this->parameters                   = $this->createMock(ParameterBagInterface::class);
        $this->security                      = $this->createMock(Security::class);
        $this->countriesCategoriesRepository = $this->createMock(CountriesCategoriesRepository::class);
        $this->regionsCategoriesRepository   = $this->createMock(RegionsCategoriesRepository::class);
        $this->citiesCategoriesRepository    = $this->createMock(CitiesCategoriesRepository::class);
        $this->countriesRepository           = $this->createMock(CountriesRepository::class);
        $this->regionsRepository             = $this->createMock(RegionsRepository::class);
        $this->citiesRepository              = $this->createMock(CitiesRepository::class);
        $this->categoriesRepository          = $this->createMock(CategoriesRepository::class);

        $tenant = $this->createMock(Tenants::class);
        $tenant->method('getId')->willReturn(Uuid::fromString('11111111-1111-1111-1111-111111111111'));
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getTenantCache')->willReturn($tenant);

        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn(Uuid::fromString('22222222-2222-2222-2222-222222222222'));
        $this->security->method('getUser')->willReturn($user);

        $this->parameters->method('get')->with('upload_products')->willReturn('/uploads/products/');

        $this->entity = $this->createMock(Categories::class);
        $this->entity->method('getDomain')->willReturn($this->createMock(Domains::class));

        $this->countriesCategoriesRepository->method('findBy')->willReturn([]);
        $this->regionsCategoriesRepository->method('findBy')->willReturn([]);
        $this->citiesCategoriesRepository->method('findBy')->willReturn([]);

        $this->useCase = new EditCategoriesUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
            $this->imageUtil,
            $this->parameters,
            $this->security,
            $this->getDomainData,
            $this->countriesCategoriesRepository,
            $this->regionsCategoriesRepository,
            $this->citiesCategoriesRepository,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->categoriesRepository,
        );
    }

    private function stubEntityGetters(): void
    {
        $this->entity->method('getName')->willReturn('Electrónica');
        $this->entity->method('getDescription')->willReturn('Descripción original');
        $this->entity->method('isActive')->willReturn(true);
        $this->entity->method('getId')->willReturn(Uuid::v4());
    }

    public function testHandlerReturnsFormReturnInstance(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->stubEntityGetters();

        $result = $this->useCase->handler($this->entity);

        $this->assertInstanceOf(FormReturn::class, $result);
    }

    public function testHandlerOnGetPopulatesFormWithEntityData(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->stubEntityGetters();

        $this->childForm->expects($this->atLeastOnce())->method('setData');

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithValidSubmissionEditsCategory(): void
    {
        $editData = [
            'name'        => 'Electrónica y Gadgets',
            'description' => 'Nueva descripción',
            'activated'   => true,
            'countries'   => [],
            'regions'     => [],
            'cities'      => [],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('categories', [])->willReturn($editData);

        $this->stubEntityGetters();
        $this->entity->method('edit')->willReturnSelf();
        $this->entity->expects($this->once())->method('activate');

        $this->countriesCategoriesRepository->expects($this->once())->method('invalidateSelectedIdsCache');
        $this->regionsCategoriesRepository->expects($this->once())->method('invalidateSelectedIdsCache');
        $this->citiesCategoriesRepository->expects($this->once())->method('invalidateSelectedIdsCache');
        $this->categoriesRepository->expects($this->once())->method('invalidatePublicCache');

        $this->entityManager->expects($this->once())->method('add');
        $this->translator->method('trans')->willReturn('Categoría editada');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertSame('Categoría editada', $result->getMessage());
    }

    public function testHandlerWithValidSubmissionDeactivatesCategory(): void
    {
        $editData = [
            'name'        => 'Electrónica',
            'description' => 'Desc',
            'activated'   => false,
            'countries'   => [],
            'regions'     => [],
            'cities'      => [],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('categories', [])->willReturn($editData);

        $this->stubEntityGetters();
        $this->entity->method('edit')->willReturnSelf();
        $this->entity->expects($this->once())->method('deactivate');
        $this->entity->expects($this->never())->method('activate');

        $this->translator->method('trans')->willReturn('Categoría editada');

        $this->useCase->handler($this->entity);
    }

    public function testHandlerWithSubmittedInvalidFormDoesNotPersist(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCatchesExceptionAndReturnsError(): void
    {
        $editData = [
            'name'        => 'Electrónica',
            'description' => 'Desc',
            'activated'   => true,
            'countries'   => [],
            'regions'     => [],
            'cities'      => [],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('categories', [])->willReturn($editData);

        $this->stubEntityGetters();
        $this->entity->method('edit')->willThrowException(new \RuntimeException('Error inesperado'));

        $this->log->method('handler')->willReturn(null);
        $this->translator->method('trans')->willReturn('Error al editar');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
