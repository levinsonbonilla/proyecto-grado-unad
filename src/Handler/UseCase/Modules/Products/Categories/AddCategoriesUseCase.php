<?php

namespace App\Handler\UseCase\Modules\Products\Categories;

use App\ArgumentHandler\CategoriesArgument;
use App\Entity\Configurations\Cities\CitiesCategories;
use App\Entity\Configurations\Countries\CountriesCategories;
use App\Entity\Configurations\Regions\RegionsCategories;
use App\Entity\Products\Categories\Categories;
use App\Form\Modules\Products\CategoriesType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Products\Categories\AddCategoriesInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Products\Categories\CategoriesRepository;
use App\ReturnHandler\FormReturn;
use App\Util\ImageUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddCategoriesUseCase extends AbstractAddHandler implements AddCategoriesInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly ImageUtil $imageUtils,
        private readonly ParameterBagInterface $parameters,
        private readonly Security $security,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly CategoriesRepository $categoriesRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(): FormReturn
    {
        return $this->process(CategoriesType::class);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("category_created_successfully", [], 'modules');
    }

    protected function add(array $data, array $additionalData): void
    {
        $image = $this->request->getCurrentRequest()->files->get("categories", [])["image"] ?? [];
        if (!empty($image)) {
            $dir = $this->parameters->get("upload_products") . $this->getDomainData->getTenantCache()->getId() . '/' . $this->security->getUser()->getId() . '/';
            $data["image"] = $this->imageUtils->addImage(dir: $dir, file: $image);
        }

        $domain = $this->getDomainData->getDomain();
        $categorie = new Categories();
        $argument = new CategoriesArgument($data, $domain);
        $categorie->add($argument);
        $this->customeEntityManager->add($categorie, true);

        foreach (array_filter((array)($data['countries'] ?? [])) as $id) {
            $country = $this->countriesRepository->find($id);
            if ($country) {
                $this->customeEntityManager->add((new CountriesCategories())->add($country, $categorie), false);
            }
        }
        foreach (array_filter((array)($data['regions'] ?? [])) as $id) {
            $region = $this->regionsRepository->find($id);
            if ($region) {
                $this->customeEntityManager->add((new RegionsCategories())->add($region, $categorie), false);
            }
        }
        foreach (array_filter((array)($data['cities'] ?? [])) as $id) {
            $city = $this->citiesRepository->find($id);
            if ($city) {
                $this->customeEntityManager->add((new CitiesCategories())->add($city, $categorie), false);
            }
        }

        $this->customeEntityManager->flush();

        $this->categoriesRepository->invalidatePublicCache($domain);
    }
}
