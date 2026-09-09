<?php

namespace App\Handler\UseCase\Modules\Products\Manager;

use App\ArgumentHandler\ImageProductArgument;
use App\ArgumentHandler\ProductsArgument;
use App\Entity\Products\Categories\ProductsCategories;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Products;
use App\Form\Modules\Products\ManagersType;
use App\Form\Modules\Products\ProductVariantImageType;
use App\Form\Modules\Products\ProductVariantMedidaType;
use App\Form\Modules\Products\ProductVariantType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Products\Manager\EditManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Categories\CategoriesRepository;
use App\Repository\Products\Categories\ProductsCategoriesRepository;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ImagesProductsRepository;
use App\Repository\Products\ProductsRepository;
use App\ReturnHandler\FormReturn;
use App\Service\Products\ProductVariantBlockRowResolverInterface;
use App\Service\Products\ProductVariantStockValidatorInterface;
use App\Util\ArrayUtil;
use App\Util\ImageUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditManagerUseCase extends AbstractEditHandler implements EditManagerInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly ProductsCategoriesRepository $productsCategoriesRepository,
        private readonly ImagesProductsRepository $imagesProductsRepository,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CategoriesRepository $categoriesRepository,
        private readonly ProductVariantBlockRowResolverInterface $blockRowResolver,
        private readonly ProductVariantStockValidatorInterface $variantStockValidator,
        private readonly ProductsColorsRepository $productsColorsRepository,
        private readonly ProductsRepository $productsRepository,
        private readonly ImageUtil $imageUtil,
        private readonly ParameterBagInterface $parameters,
        private readonly Security $security,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Products $entity): FormReturn
    {
        return $this->process($entity);
    }

    protected function formType(): string
    {
        return ManagersType::class;
    }

    protected function formName(): string
    {
        return 'managers';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("product_edited_success", [], 'modules');
    }

    protected function complementForm(FormInterface $form): FormInterface
    {
        $yes = $this->translator->trans("yes", [], "users");
        $no = $this->translator->trans("no", [], "users");

        return $form->add('activated', ChoiceType::class, [
            'choices' => [$yes => true, $no => false],
            'required' => true,
        ]);
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {
        $categoriesProducts = $this->productsCategoriesRepository->findBy([
            'product' => $entity,
            'active' => true
        ]);

        $categories = array_map(fn($pc) => $pc->getCategory(), $categoriesProducts);

        $form->get('name')->setData($entity->getName());
        $form->get('description')->setData($entity->getDescription());
        $form->get('basePrice')->setData($entity->getBasePrice());
        $form->get('publicPrice')->setData($entity->getPublicPrice());
        $form->get('stock')->setData($entity->getStock());
        $form->get('activated')->setData($entity->isActive());
        $form->get('productsCategories')->setData($categories);

        $this->assembleVariants($form, $entity);

        return $form;
    }

    private function assembleVariants(FormInterface $form, Products $entity): void
    {
        $images = $this->imagesProductsRepository->findBy(
            ['product' => $entity, 'active' => true],
            ['orderColumn' => 'ASC']
        );

        $imagesByBlockId = [];
        foreach ($images as $imageEntity) {
            $block = $imageEntity->getProductColor();
            $blockKey = $block !== null ? (string) $block->getId() : '__no_block__';
            $imagesByBlockId[$blockKey][] = $imageEntity;
        }

        $visualGroups = [];
        foreach ($imagesByBlockId as $blockIdKey => $groupImages) {
            $block = $groupImages[0]->getProductColor();
            $groupId = $block?->getVariantGroupId();
            $mergeKey = $groupId !== null ? 'group:' . $groupId : 'solo:' . $blockIdKey;
            $visualGroups[$mergeKey]['members'][] = ['block' => $block, 'images' => $groupImages];
        }

        $blockIndex = 0;
        foreach ($visualGroups as $group) {
            $members = $group['members'];

            usort($members, fn (array $a, array $b) => ($a['block']?->getMedida()?->getName() ?? '') <=> ($b['block']?->getMedida()?->getName() ?? ''));
            $primary = $members[0];
            $anyBlock = $primary['block'];

            $form->get('variants')->add($blockIndex, ProductVariantType::class);
            $variantForm = $form->get('variants')->get($blockIndex);

            if ($anyBlock !== null && $anyBlock->getColor() !== null) {
                $variantForm->get('colorName')->setData($anyBlock->getColor()->getName());
                $variantForm->get('colorHex')->setData($anyBlock->getColor()->getHexCode());
            }

            if ($anyBlock !== null) {
                $variantForm->get('order')->setData($anyBlock->getOrderColumn());
            }

            if (count($members) === 1 && ($anyBlock === null || $anyBlock->getMedida() === null)) {

                if ($anyBlock !== null) {
                    $variantForm->get('rowId')->setData((string) $anyBlock->getId());
                    $variantForm->get('stock')->setData($anyBlock->getStock());
                }
            } else {

                $medidaIndex = 0;
                foreach ($members as $member) {
                    $variantForm->get('medidas')->add($medidaIndex, ProductVariantMedidaType::class);
                    $medidaForm = $variantForm->get('medidas')->get($medidaIndex);
                    $medidaForm->get('rowId')->setData((string) $member['block']->getId());
                    $medidaForm->get('medidaName')->setData($member['block']->getMedida()->getName());
                    $medidaForm->get('stock')->setData($member['block']->getStock());
                    $medidaIndex++;
                }
            }

            $imageIndex = 0;
            foreach ($primary['images'] as $imageEntity) {
                $variantForm->get('images')->add($imageIndex, ProductVariantImageType::class, ['data' => $imageEntity]);
                $variantForm->get('images')->get($imageIndex)->get('id')->setData((string) $imageEntity->getId());
                $variantForm->get('images')->get($imageIndex)->get('position')->setData($imageEntity->getOrderColumn());
                $variantForm->get('images')->get($imageIndex)->get('currentImage')->setData($imageEntity->getImage());
                $imageIndex++;
            }

            $blockIndex++;
        }
    }

    protected function edit(array $data, object $entity): void
    {
        $stockRaw = ArrayUtil::validateExistKey($data, "stock");
        $generalStock = ($stockRaw !== null && trim($stockRaw) !== '') ? (int) $stockRaw : null;
        $this->variantStockValidator->validate($generalStock, $data["variants"] ?? []);

        $argument = new ProductsArgument($data, $this->getDomainData->getDomain());
        $entity->edit($argument);
        !empty($data["activated"]) ? $entity->activate() : $entity->deactivate();
        $this->customeEntityManager->add($entity);

        $this->syncCategories($data, $entity);
        $this->editVariants($data["variants"] ?? [], $entity);

        $this->customeEntityManager->flush();

        $this->productsRepository->invalidatePublicHomeCache($entity->getDomain());
    }

    private function syncCategories(array $data, Products $products): void
    {
        $incomingCategoryIds = array_map(fn($v) => (string)$v, $data["productsCategories"] ?? []);

        $existingLinks = $this->productsCategoriesRepository->findBy(['product' => $products]);
        $existingCatsById = [];
        foreach ($existingLinks as $link) {
            $cat = method_exists($link, 'getCategory') ? $link->getCategory() : null;
            $cid = $cat ? (string)$cat->getId() : null;
            if ($cid !== null) {
                $existingCatsById[$cid] = $link;
            }
        }

        foreach ($incomingCategoryIds as $categoryId) {
            $category = $this->categoriesRepository->find($categoryId);
            if (!$category) {
                continue;
            }
            if (isset($existingCatsById[$categoryId])) {
                $existingCatsById[$categoryId]->activate();
                $this->customeEntityManager->add($existingCatsById[$categoryId]);
            } else {
                $productCategory = new ProductsCategories();
                $productCategory->add($products, $category);
                $this->customeEntityManager->add($productCategory);
            }
        }

        foreach ($existingCatsById as $cid => $link) {
            if (!in_array($cid, $incomingCategoryIds, true)) {
                $link->deactivate();
                $this->customeEntityManager->add($link);
            }
        }
    }

    private function editVariants(array $variants, Products $product): void
    {
        $filesRoot = $this->request->getCurrentRequest()->files->get("managers", [])["variants"] ?? [];
        $domain = $this->getDomainData->getDomain();
        $dir = $this->parameters->get("upload_products") . $this->getDomainData->getTenantCache()->getId() . '/' . $this->security->getUser()->getId() . '/';

        $existingImages = $this->imagesProductsRepository->findBy(['product' => $product]);
        $existingImagesById = [];
        foreach ($existingImages as $img) {
            $existingImagesById[(string) $img->getId()] = $img;
        }
        $incomingImageIds = [];

        $existingBlocks = $this->productsColorsRepository->findBy(['product' => $product]);
        $existingBlocksById = [];
        foreach ($existingBlocks as $block) {
            $existingBlocksById[(string) $block->getId()] = $block;
        }

        $touchedBlocks = new \SplObjectStorage();

        foreach ($variants as $blockKey => $block) {
            $images = $block["images"] ?? [];
            if (empty($images)) {
                continue;
            }

            $blockFiles = $filesRoot[$blockKey]["images"] ?? [];
            $productColors = [];

            foreach ($images as $imageKey => $imageRow) {
                $file = $blockFiles[$imageKey]["image"] ?? null;
                $incomingId = $imageRow['id'] ?? null;

                $imageUrl = $file
                    ? $this->imageUtil->addImage(dir: $dir, file: $file)
                    : ($imageRow["currentImage"] ?? null);

                if ($productColors === []) {
                    $productColors = $this->blockRowResolver->resolve($block, $imageUrl, $product, $domain, $existingBlocksById);
                    foreach ($productColors as $productColor) {
                        $touchedBlocks->attach($productColor);
                    }
                }

                $primary = $productColors[0] ?? null;
                $secondary = array_slice($productColors, 1);

                $imageDataBase = [
                    "description" => $imageRow["description"] ?? null,
                    "orderColumn" => $imageRow["position"] ?? null,
                    "image" => $imageUrl,
                ];

                if ($incomingId && isset($existingImagesById[(string) $incomingId])) {
                    $imgEntity = $existingImagesById[(string) $incomingId];
                    $imgEntity->edit(new ImageProductArgument(data: $imageDataBase + ["productColor" => $primary], product: $product));
                    $imgEntity->activate();
                    $this->customeEntityManager->add($imgEntity);
                    $incomingImageIds[] = (string) $incomingId;
                } else {
                    $imgEntity = new ImagesProducts();
                    $imgEntity->add(new ImageProductArgument(data: $imageDataBase + ["productColor" => $primary], product: $product));
                    $this->customeEntityManager->add($imgEntity);
                }

                foreach ($secondary as $productColor) {
                    $imgEntity = new ImagesProducts();
                    $imgEntity->add(new ImageProductArgument(data: $imageDataBase + ["productColor" => $productColor], product: $product));
                    $this->customeEntityManager->add($imgEntity);
                }
            }
        }

        foreach ($existingImagesById as $eid => $imgEntity) {
            if (!in_array((string) $eid, $incomingImageIds, true)) {
                $imgEntity->deactivate();
                $this->customeEntityManager->add($imgEntity);
            }
        }

        foreach ($existingBlocksById as $blockRow) {
            if (!$touchedBlocks->contains($blockRow)) {
                $blockRow->deactivate();
                $this->customeEntityManager->add($blockRow);
            }
        }
    }
}
