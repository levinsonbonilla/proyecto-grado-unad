<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Manager;

use App\Entity\Products\Categories\Categories;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Handler\UseCase\Modules\Products\Manager\AddManagerUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Categories\CategoriesRepository;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddManagerUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private GetDomainDataInterface&MockObject $getDomainData;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private CategoriesRepository&MockObject $categoriesRepository;
    private ProductVariantBlockRowResolverInterface&MockObject $blockRowResolver;
    private ProductVariantStockValidatorInterface&MockObject $variantStockValidator;
    private ProductsRepository&MockObject $productsRepository;
    private S3ManagerInterface&MockObject $s3Manager;
    private ImageUtil $imageUtil;
    private ParameterBagInterface&MockObject $parameters;
    private Security&MockObject $security;
    private AddManagerUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('managers');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request        = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);
        $this->requestStack   = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log         = $this->createMock(LogInterface::class);
        $this->translator  = $this->createMock(TranslatorInterface::class);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);

        $this->categoriesRepository = $this->createMock(CategoriesRepository::class);
        $this->blockRowResolver     = $this->createMock(ProductVariantBlockRowResolverInterface::class);

        $this->variantStockValidator = $this->createMock(ProductVariantStockValidatorInterface::class);
        $this->productsRepository   = $this->createMock(ProductsRepository::class);

        $this->s3Manager             = $this->createMock(S3ManagerInterface::class);
        $this->imageUtil            = new ImageUtil($this->s3Manager);
        $this->parameters           = $this->createMock(ParameterBagInterface::class);
        $this->security             = $this->createMock(Security::class);

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

        $this->useCase = new AddManagerUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->getDomainData,
            $this->entityManager,
            $this->categoriesRepository,
            $this->blockRowResolver,
            $this->variantStockValidator,
            $this->productsRepository,
            $this->imageUtil,
            $this->parameters,
            $this->security,
        );
    }

    private function baseFormData(): array
    {
        return [
            'name'        => 'Producto Nuevo',
            'description' => '<p>Descripción</p>',
            'basePrice'   => '50000',
            'publicPrice' => '70000',
        ];
    }

    private function withUploadedFiles(int $blockKey, int $count): void
    {
        $this->s3Manager->method('create')->willReturn('http://localhost/foto.jpg');
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[$i] = ['image' => $this->createMock(UploadedFile::class)];
        }
        $this->request->files = new FileBag([
            'managers' => ['variants' => [$blockKey => ['images' => $images]]],
        ]);
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler();

        $this->assertFalse($result->isProcess());
        $this->assertNull($result->getMessage());
    }

    public function testHandlerCreatesProductWithoutCategory(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($this->baseFormData());

        $this->categoriesRepository->expects($this->never())->method('find');

        $this->entityManager->expects($this->once())->method('add');
        $this->entityManager->expects($this->once())->method('flush');
        $this->productsRepository->expects($this->once())->method('invalidatePublicHomeCache');
        $this->translator->method('trans')->willReturn('Creado');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCreatesProductWithCategory(): void
    {
        $categoryId  = '660e8400-e29b-41d4-a716-446655440001';
        $data        = $this->baseFormData();
        $data['productsCategories'] = [$categoryId];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);

        $category = $this->createMock(Categories::class);
        $this->categoriesRepository->expects($this->once())->method('find')->with($categoryId)->willReturn($category);

        $this->entityManager->expects($this->exactly(2))->method('add');
        $this->translator->method('trans')->willReturn('Creado');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCreatesProductWithStock(): void
    {
        $data = $this->baseFormData();
        $data['stock'] = '5';

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Creado');

        $this->entityManager->expects($this->once())->method('add')->with(
            $this->callback(fn ($product) => $product instanceof \App\Entity\Products\Products && $product->getStock() === 5)
        );

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCreatesProductWithoutStockLeavesItUntracked(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($this->baseFormData());
        $this->translator->method('trans')->willReturn('Creado');

        $this->entityManager->expects($this->once())->method('add')->with(
            $this->callback(fn ($product) => $product instanceof \App\Entity\Products\Products && $product->getStock() === null)
        );

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerReturnsErrorWhenVariantStockExceedsGeneralStock(): void
    {
        $data = $this->baseFormData();
        $data['stock'] = '10';
        $data['variants'] = [
            ['stock' => '6', 'images' => [['position' => '1']]],
            ['stock' => '5', 'images' => [['position' => '2']]],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);

        $this->variantStockValidator->method('validate')
            ->with(10, $data['variants'])
            ->willThrowException(new GenericException('Excede el stock disponible', 400));

        $this->entityManager->expects($this->never())->method('add');
        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
        $this->assertSame('Excede el stock disponible', $result->getMessage());
    }

    public function testHandlerSkipsBlockWithoutImages(): void
    {
        $data = $this->baseFormData();
        $data['variants'] = [
            ['colorName' => 'Negro', 'stock' => '5', 'images' => []],
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Creado');

        $this->blockRowResolver->expects($this->never())->method('resolve');

        $this->entityManager->expects($this->once())->method('add');

        $result = $this->useCase->handler();

        $this->assertFalse($result->isError());
    }

    public function testHandlerCreatesOneVariantRowSharedByAllPhotosInBlock(): void
    {
        $data = $this->baseFormData();
        $data['variants'] = [
            ['colorName' => 'Negro', 'colorHex' => '#000000', 'stock' => '10', 'images' => [
                ['position' => '1'],
                ['position' => '2'],
            ]],
        ];

        $this->withUploadedFiles(0, 2);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Creado');

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

        $result = $this->useCase->handler();

        $this->assertFalse($result->isError());
        $this->assertCount(2, $taggedColors);
        $this->assertSame($row, $taggedColors[0]);
        $this->assertSame($row, $taggedColors[1]);
    }

    public function testHandlerTagsImagesWithNullWhenResolverReturnsNoRows(): void
    {
        $data = $this->baseFormData();
        $data['variants'] = [
            ['colorName' => '', 'stock' => '', 'images' => [['position' => '1']]],
        ];

        $this->withUploadedFiles(0, 1);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Creado');

        $this->blockRowResolver->method('resolve')->willReturn([]);

        $addedColorRows = [];
        $this->entityManager->method('add')->willReturnCallback(
            function ($entity) use (&$addedColorRows) {
                if ($entity instanceof ProductsColors) {
                    $addedColorRows[] = $entity;
                }
                if ($entity instanceof ImagesProducts) {
                    $this->assertNull($entity->getProductColor());
                }
            }
        );

        $result = $this->useCase->handler();

        $this->assertFalse($result->isError());
        $this->assertCount(0, $addedColorRows);
    }

    public function testHandlerCreatesMultipleVariantRowsForOneBlockWithMultipleMedidas(): void
    {
        $data = $this->baseFormData();
        $data['variants'] = [
            [
                'colorName' => 'Rosa',
                'medidas' => [
                    ['medidaName' => 'S', 'stock' => '4'],
                    ['medidaName' => 'M', 'stock' => '6'],
                    ['medidaName' => 'L', 'stock' => '2'],
                ],
                'images' => [
                    ['position' => '1'],
                    ['position' => '2'],
                ],
            ],
        ];

        $this->withUploadedFiles(0, 2);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($data);
        $this->translator->method('trans')->willReturn('Creado');

        $rowS = $this->createMock(ProductsColors::class);
        $rowM = $this->createMock(ProductsColors::class);
        $rowL = $this->createMock(ProductsColors::class);
        $this->blockRowResolver->expects($this->once())->method('resolve')->willReturn([$rowS, $rowM, $rowL]);

        $taggedColors = [];
        $this->entityManager->method('add')->willReturnCallback(
            function ($entity) use (&$taggedColors) {
                if ($entity instanceof ImagesProducts) {
                    $taggedColors[] = $entity->getProductColor();
                }
            }
        );

        $result = $this->useCase->handler();

        $this->assertFalse($result->isError());
        $this->assertCount(6, $taggedColors);
        $this->assertCount(2, array_filter($taggedColors, fn ($pc) => $pc === $rowS));
        $this->assertCount(2, array_filter($taggedColors, fn ($pc) => $pc === $rowM));
        $this->assertCount(2, array_filter($taggedColors, fn ($pc) => $pc === $rowL));
    }

    public function testHandlerWithInvalidFormDoesNotCreateProduct(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertNull($result->getMessage());
    }

    public function testHandlerReturnsGenericErrorOnException(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('managers', [])->willReturn($this->baseFormData());

        $this->entityManager->method('add')->willThrowException(new \RuntimeException('DB error'));
        $this->log->expects($this->once())->method('handler');
        $this->translator->method('trans')->willReturn('Ocurrió un error');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
