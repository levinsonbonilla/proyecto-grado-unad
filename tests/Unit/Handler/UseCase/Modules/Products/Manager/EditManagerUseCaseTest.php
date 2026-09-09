<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Manager;

use App\Entity\Products\Categories\Categories;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Handler\UseCase\Modules\Products\Manager\EditManagerUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Categories\CategoriesRepository;
use App\Repository\Products\Categories\ProductsCategoriesRepository;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ImagesProductsRepository;
use App\Repository\Products\ProductsRepository;
use App\Service\Products\ProductVariantBlockRowResolverInterface;
use App\Service\Products\ProductVariantStockValidatorInterface;
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

class EditManagerUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private FormInterface&MockObject $childForm;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private ProductsCategoriesRepository&MockObject $productsCategoriesRepository;
    private ImagesProductsRepository&MockObject $imagesProductsRepository;
    private GetDomainDataInterface&MockObject $getDomainData;
    private CategoriesRepository&MockObject $categoriesRepository;
    private ProductVariantBlockRowResolverInterface&MockObject $blockRowResolver;
    private ProductVariantStockValidatorInterface&MockObject $variantStockValidator;
    private ProductsColorsRepository&MockObject $productsColorsRepository;
    private ProductsRepository&MockObject $productsRepository;
    private ImageUtil $imageUtil;
    private ParameterBagInterface&MockObject $parameters;
    private Security&MockObject $security;
    private Products&MockObject $entity;
    private EditManagerUseCase $useCase;

    protected function setUp(): void
    {
        $this->childForm = $this->createMock(FormInterface::class);
        $this->childForm->method('setData')->willReturnSelf();

        $this->childForm->method('get')->willReturnSelf();
        $this->childForm->method('add')->willReturnSelf();

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

        $this->log           = $this->createMock(LogInterface::class);
        $this->translator    = $this->createMock(TranslatorInterface::class);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);

        $this->productsCategoriesRepository = $this->createMock(ProductsCategoriesRepository::class);
        $this->imagesProductsRepository     = $this->createMock(ImagesProductsRepository::class);
        $this->categoriesRepository         = $this->createMock(CategoriesRepository::class);
        $this->blockRowResolver              = $this->createMock(ProductVariantBlockRowResolverInterface::class);

        $this->variantStockValidator        = $this->createMock(ProductVariantStockValidatorInterface::class);
        $this->productsColorsRepository     = $this->createMock(ProductsColorsRepository::class);
        $this->productsRepository           = $this->createMock(ProductsRepository::class);

        $this->imageUtil                    = new ImageUtil($this->createMock(S3ManagerInterface::class));
        $this->parameters                   = $this->createMock(ParameterBagInterface::class);
        $this->security                     = $this->createMock(Security::class);

        $this->productsCategoriesRepository->method('findBy')->willReturn([]);
        $this->imagesProductsRepository->method('findBy')->willReturn([]);
        $this->productsColorsRepository->method('findBy')->willReturn([]);

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

        $this->entity = $this->createMock(Products::class);
        $this->entity->method('getDomain')->willReturn($domain);

        $this->useCase = $this->makeUseCase();
    }

    private function makeUseCase(array $overrides = []): EditManagerUseCase
    {
        return new EditManagerUseCase(
            $overrides['requestStack'] ?? $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $overrides['entityManager'] ?? $this->entityManager,
            $overrides['productsCategoriesRepository'] ?? $this->productsCategoriesRepository,
            $overrides['imagesProductsRepository'] ?? $this->imagesProductsRepository,
            $this->getDomainData,
            $this->categoriesRepository,
            $this->blockRowResolver,
            $this->variantStockValidator,
            $overrides['productsColorsRepository'] ?? $this->productsColorsRepository,
            $this->productsRepository,
            $this->imageUtil,
            $this->parameters,
            $this->security,
        );
    }

    private function baseFormData(): array
    {
        return [
            'name'        => 'Producto Editado',
            'description' => '<p>Descripción</p>',
            'basePrice'   => '50000',
            'publicPrice' => '70000',
            'activated'   => '1',
        ];
    }

    public function testHandlerOnGetPopulatesFormWithoutCategories(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->entity->method('getName')->willReturn('Producto Original');
        $this->entity->method('getDescription')->willReturn('desc');
        $this->entity->method('getBasePrice')->willReturn('40000');
        $this->entity->method('getPublicPrice')->willReturn('60000');
        $this->entity->method('isActive')->willReturn(true);

        $this->childForm->expects($this->atLeastOnce())->method('setData');

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isProcess());
    }

    public function testHandlerEditWithoutCategoryKeyDoesNotThrow(): void
    {
        $data = $this->baseFormData();

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);

        $this->categoriesRepository->expects($this->never())->method('find');
        $this->entityManager->expects($this->once())->method('flush');
        $this->translator->method('trans')->willReturn('Actualizado');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerEditSyncsCategoryFromData(): void
    {
        $categoryId = 'real-category-id';

        $data = $this->baseFormData();
        $data['productsCategories'] = [$categoryId];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);

        $category = $this->createMock(Categories::class);

        $this->categoriesRepository->expects($this->once())->method('find')->with($categoryId)->willReturn($category);
        $this->translator->method('trans')->willReturn('Actualizado');

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isError());
    }

    public function testHandlerDeactivatesEntityWhenActivatedIsFalsy(): void
    {
        $data = $this->baseFormData();
        $data['activated'] = '0';

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);

        $this->entity->expects($this->once())->method('deactivate');
        $this->entity->expects($this->never())->method('activate');
        $this->translator->method('trans')->willReturn('Actualizado');

        $this->useCase->handler($this->entity);
    }

    public function testHandlerOnGetPrefillsStockField(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->entity->method('getName')->willReturn('Producto Original');
        $this->entity->method('getDescription')->willReturn('desc');
        $this->entity->method('getBasePrice')->willReturn('40000');
        $this->entity->method('getPublicPrice')->willReturn('60000');
        $this->entity->method('isActive')->willReturn(true);
        $this->entity->expects($this->once())->method('getStock')->willReturn(7);

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isProcess());
    }

    public function testHandlerEditUpdatesStock(): void
    {
        $data = $this->baseFormData();
        $data['stock'] = '5';

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Actualizado');

        $this->entity->expects($this->once())->method('edit')->with(
            $this->callback(fn ($argument) => $argument->getValue('stock') === 5)
        );

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isError());
    }

    public function testHandlerEditWithoutStockClearsTracking(): void
    {
        $data = $this->baseFormData();

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Actualizado');

        $this->entity->expects($this->once())->method('edit')->with(
            $this->callback(fn ($argument) => $argument->getValue('stock') === null)
        );

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isError());
    }

    public function testHandlerEditReusesExistingImageByIdInsteadOfDuplicating(): void
    {
        $imageId = '770e8400-e29b-41d4-a716-446655440002';
        $existingImage = $this->createMock(ImagesProducts::class);
        $existingImage->method('getId')->willReturn(Uuid::fromString($imageId));
        $existingImage->expects($this->once())->method('edit');
        $existingImage->expects($this->once())->method('activate');
        $existingImage->expects($this->never())->method('deactivate');

        $imagesProductsRepository = $this->createMock(ImagesProductsRepository::class);
        $imagesProductsRepository->method('findBy')->willReturn([$existingImage]);

        $data = $this->baseFormData();
        $data['variants'] = [
            ['images' => [
                ['id' => $imageId, 'currentImage' => 'http://localhost/foto.jpg', 'position' => '1'],
            ]],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Actualizado');

        $useCase = $this->makeUseCase(['imagesProductsRepository' => $imagesProductsRepository]);
        $result = $useCase->handler($this->entity);

        $this->assertFalse($result->isError());
    }

    public function testHandlerEditCreatesOneVariantRowSharedByAllPhotosInBlock(): void
    {
        $data = $this->baseFormData();
        $data['variants'] = [
            ['colorName' => 'Negro', 'colorHex' => '#000000', 'stock' => '10', 'images' => [
                ['currentImage' => 'http://localhost/negro-1.jpg', 'position' => '1'],
                ['currentImage' => 'http://localhost/negro-2.jpg', 'position' => '2'],
            ]],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Actualizado');

        $row = $this->createMock(ProductsColors::class);
        $this->blockRowResolver->expects($this->once())->method('resolve')->willReturn([$row]);

        $taggedColors = [];
        $this->entityManager->method('add')->willReturnCallback(
            function ($entity) use (&$taggedColors) {
                if ($entity instanceof ImagesProducts) {
                    $taggedColors[] = $entity->getProductColor();
                }
            }
        );

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isError());
        $this->assertCount(2, $taggedColors);
        $this->assertSame($row, $taggedColors[0]);
        $this->assertSame($row, $taggedColors[1]);
    }

    public function testHandlerEditAddsExtraMedidaRowToExistingSingleMedidaBlock(): void
    {
        $existingBlockId = '110e8400-e29b-41d4-a716-446655440010';
        $existingBlock = $this->createMock(ProductsColors::class);
        $existingBlock->method('getId')->willReturn(Uuid::fromString($existingBlockId));
        $existingBlock->expects($this->never())->method('deactivate');

        $productsColorsRepository = $this->createMock(ProductsColorsRepository::class);
        $productsColorsRepository->method('findBy')->willReturn([$existingBlock]);

        $imageId = '220e8400-e29b-41d4-a716-446655440020';
        $existingImage = $this->createMock(ImagesProducts::class);
        $existingImage->method('getId')->willReturn(Uuid::fromString($imageId));

        $existingImage->expects($this->once())->method('edit')->with(
            $this->callback(fn ($argument) => $argument->getValue('productColor') === $existingBlock)
        );
        $existingImage->expects($this->once())->method('activate');

        $imagesProductsRepository = $this->createMock(ImagesProductsRepository::class);
        $imagesProductsRepository->method('findBy')->willReturn([$existingImage]);

        $rowM = $this->createMock(ProductsColors::class);
        $rowL = $this->createMock(ProductsColors::class);
        $this->blockRowResolver->expects($this->once())->method('resolve')
            ->willReturn([$existingBlock, $rowM, $rowL]);

        $data = $this->baseFormData();
        $data['variants'] = [
            [
                'rowId' => $existingBlockId,
                'colorName' => 'Rosa',
                'medidas' => [
                    ['rowId' => $existingBlockId, 'medidaName' => 'S', 'stock' => '4'],
                    ['medidaName' => 'M', 'stock' => '6'],
                    ['medidaName' => 'L', 'stock' => '2'],
                ],
                'images' => [
                    ['id' => $imageId, 'currentImage' => 'http://localhost/rosa.jpg', 'position' => '1'],
                ],
            ],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Actualizado');

        $freshlyTaggedColors = [];
        $this->entityManager->method('add')->willReturnCallback(
            function ($entity) use (&$freshlyTaggedColors, $existingImage) {
                if ($entity instanceof ImagesProducts && $entity !== $existingImage) {
                    $freshlyTaggedColors[] = $entity->getProductColor();
                }
            }
        );

        $useCase = $this->makeUseCase([
            'imagesProductsRepository' => $imagesProductsRepository,
            'productsColorsRepository' => $productsColorsRepository,
        ]);
        $result = $useCase->handler($this->entity);

        $this->assertFalse($result->isError());

        $this->assertCount(2, $freshlyTaggedColors);
        $this->assertSame($rowM, $freshlyTaggedColors[0]);
        $this->assertSame($rowL, $freshlyTaggedColors[1]);
    }

    public function testHandlerEditRemovesMedidaRowDeactivatesThatBlock(): void
    {
        $rowS = $this->createMock(ProductsColors::class);
        $rowS->method('getId')->willReturn(Uuid::fromString('330e8400-e29b-41d4-a716-446655440030'));
        $rowS->expects($this->never())->method('deactivate');

        $rowM = $this->createMock(ProductsColors::class);
        $rowM->method('getId')->willReturn(Uuid::fromString('440e8400-e29b-41d4-a716-446655440040'));
        $rowM->expects($this->never())->method('deactivate');

        $rowL = $this->createMock(ProductsColors::class);
        $rowL->method('getId')->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440050'));
        $rowL->expects($this->once())->method('deactivate');

        $productsColorsRepository = $this->createMock(ProductsColorsRepository::class);
        $productsColorsRepository->method('findBy')->willReturn([$rowS, $rowM, $rowL]);

        $this->blockRowResolver->method('resolve')->willReturn([$rowS, $rowM]);

        $data = $this->baseFormData();
        $data['variants'] = [
            [
                'colorName' => 'Rosa',
                'medidas' => [
                    ['medidaName' => 'S', 'stock' => '4'],
                    ['medidaName' => 'M', 'stock' => '6'],
                ],
                'images' => [
                    ['currentImage' => 'http://localhost/rosa.jpg', 'position' => '1'],
                ],
            ],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Actualizado');

        $useCase = $this->makeUseCase(['productsColorsRepository' => $productsColorsRepository]);
        $result = $useCase->handler($this->entity);

        $this->assertFalse($result->isError());
    }

    public function testHandlerReturnsErrorWhenVariantStockExceedsGeneralStock(): void
    {
        $data = $this->baseFormData();
        $data['stock'] = '10';
        $data['variants'] = [
            ['stock' => '6', 'images' => [['currentImage' => 'http://localhost/a.jpg', 'position' => '1']]],
            ['stock' => '5', 'images' => [['currentImage' => 'http://localhost/b.jpg', 'position' => '2']]],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);

        $this->variantStockValidator->method('validate')
            ->with(10, $data['variants'])
            ->willThrowException(new GenericException('Excede el stock disponible', 400));

        $this->entity->expects($this->never())->method('edit');
        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
        $this->assertSame('Excede el stock disponible', $result->getMessage());
    }

    public function testHandlerEditDeactivatesBlockNotInIncomingList(): void
    {
        $blockId = '880e8400-e29b-41d4-a716-446655440003';
        $existingBlock = $this->createMock(ProductsColors::class);
        $existingBlock->method('getId')->willReturn(Uuid::fromString($blockId));
        $existingBlock->expects($this->once())->method('deactivate');

        $productsColorsRepository = $this->createMock(ProductsColorsRepository::class);
        $productsColorsRepository->method('findBy')->willReturn([$existingBlock]);
        $useCase = $this->makeUseCase(['productsColorsRepository' => $productsColorsRepository]);

        $data = $this->baseFormData();

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Actualizado');

        $result = $useCase->handler($this->entity);

        $this->assertFalse($result->isError());
    }

    public function testHandlerOnGetPrefillsMedidaNameForExistingBlock(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->entity->method('getName')->willReturn('Producto Original');
        $this->entity->method('getDescription')->willReturn('desc');
        $this->entity->method('getBasePrice')->willReturn('40000');
        $this->entity->method('getPublicPrice')->willReturn('60000');
        $this->entity->method('isActive')->willReturn(true);

        $medida = $this->createMock(Medidas::class);
        $medida->method('getName')->willReturn('M');

        $block = $this->createMock(ProductsColors::class);
        $block->method('getId')->willReturn(Uuid::fromString('990e8400-e29b-41d4-a716-446655440004'));
        $block->method('getStock')->willReturn(4);
        $block->method('getColor')->willReturn(null);
        $block->method('getMedida')->willReturn($medida);

        $image = $this->createMock(ImagesProducts::class);
        $image->method('getProductColor')->willReturn($block);
        $image->method('getId')->willReturn(Uuid::fromString('aa0e8400-e29b-41d4-a716-446655440005'));
        $image->method('getOrderColumn')->willReturn(1);
        $image->method('getImage')->willReturn('http://localhost/m.jpg');

        $imagesProductsRepository = $this->createMock(ImagesProductsRepository::class);
        $imagesProductsRepository->method('findBy')->willReturn([$image]);
        $useCase = $this->makeUseCase(['imagesProductsRepository' => $imagesProductsRepository]);

        $result = $useCase->handler($this->entity);

        $this->assertFalse($result->isProcess());
    }

    public function testHandlerReturnsGenericErrorOnException(): void
    {
        $data = $this->baseFormData();

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);

        $this->entity->method('edit')->willThrowException(new \RuntimeException('DB error'));
        $this->log->expects($this->once())->method('handler');
        $this->translator->method('trans')->willReturn('Ocurrió un error');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
