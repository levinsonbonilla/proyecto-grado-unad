<?php

namespace App\Handler\UseCase\Modules\Products\Manager;

use App\ArgumentHandler\ImageProductArgument;
use App\ArgumentHandler\ProductsArgument;
use App\Entity\Products\Categories\ProductsCategories;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Products;
use App\Form\Modules\Products\ManagersType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Products\Manager\AddManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Categories\CategoriesRepository;
use App\Repository\Products\ProductsRepository;
use App\ReturnHandler\FormReturn;
use App\Service\Products\ProductVariantBlockRowResolverInterface;
use App\Service\Products\ProductVariantStockValidatorInterface;
use App\Util\ArrayUtil;
use App\Util\ImageUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddManagerUseCase extends AbstractAddHandler implements AddManagerInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly CategoriesRepository $categoriesRepository,
        private readonly ProductVariantBlockRowResolverInterface $blockRowResolver,
        private readonly ProductVariantStockValidatorInterface $variantStockValidator,
        private readonly ProductsRepository $productsRepository,
        private readonly ImageUtil $imageUtil,
        private readonly ParameterBagInterface $parameters,
        private readonly Security $security,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(): FormReturn
    {
        return $this->process(ManagersType::class);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("product_created_successfully", [], 'modules');
    }

    protected function add(array $data, array $additionalData): void
    {
        $stockRaw = ArrayUtil::validateExistKey($data, "stock");
        $generalStock = ($stockRaw !== null && trim($stockRaw) !== '') ? (int) $stockRaw : null;
        $this->variantStockValidator->validate($generalStock, $data["variants"] ?? []);

        $domain = $this->getDomainData->getDomain();
        $product = new Products();
        $argument = new ProductsArgument($data, $domain);
        $product->add($argument);
        $this->customeEntityManager->add($product);

        foreach ($data["productsCategories"] ?? [] as $productsCategoriesId) {
            $productCategory = new ProductsCategories();
            $category = $this->categoriesRepository->find($productsCategoriesId);
            $productCategory->add($product, $category);
            $this->customeEntityManager->add($productCategory);
        }

        $this->addVariants($data["variants"] ?? [], $product);
        $this->customeEntityManager->flush();

        $this->productsRepository->invalidatePublicHomeCache($domain);
    }

    private function addVariants(array $variants, Products $product): void
    {
        $filesRoot = $this->request->getCurrentRequest()->files->get("managers", [])["variants"] ?? [];
        $dir = $this->parameters->get("upload_products") . $this->getDomainData->getTenantCache()->getId() . '/' . $this->security->getUser()->getId() . '/';
        $domain = $this->getDomainData->getDomain();

        foreach ($variants as $blockKey => $block) {
            $images = $block["images"] ?? [];
            if (empty($images)) {
                continue;
            }

            $blockFiles = $filesRoot[$blockKey]["images"] ?? [];
            $productColors = null;

            foreach ($images as $imageKey => $imageRow) {
                $file = $blockFiles[$imageKey]["image"] ?? null;
                $imageUrl = $this->imageUtil->addImage(dir: $dir, file: $file);

                if ($productColors === null) {
                    $productColors = $this->blockRowResolver->resolve($block, $imageUrl, $product, $domain);
                }

                $imageDataBase = [
                    "description" => $imageRow["description"] ?? null,
                    "orderColumn" => $imageRow["position"] ?? null,
                    "image" => $imageUrl,
                ];

                $targets = empty($productColors) ? [null] : $productColors;
                foreach ($targets as $productColor) {
                    $imageProduct = new ImagesProducts();
                    $imageProduct->add(new ImageProductArgument(data: $imageDataBase + ["productColor" => $productColor], product: $product));
                    $this->customeEntityManager->add($imageProduct);
                }
            }
        }
    }
}
