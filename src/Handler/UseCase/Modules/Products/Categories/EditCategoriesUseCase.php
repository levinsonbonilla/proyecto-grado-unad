<?php

namespace App\Handler\UseCase\Modules\Products\Categories;

use App\ArgumentHandler\CategoriesArgument;
use App\Entity\Configurations\Cities\CitiesCategories;
use App\Entity\Configurations\Countries\CountriesCategories;
use App\Entity\Configurations\Regions\RegionsCategories;
use App\Entity\Products\Categories\Categories;
use App\Form\Modules\Products\CategoriesType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Products\Categories\EditCategoriesInterface;
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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditCategoriesUseCase extends AbstractEditHandler implements EditCategoriesInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly ImageUtil $imageUtils,
        private readonly ParameterBagInterface $parameters,
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CountriesCategoriesRepository $countriesCategoriesRepository,
        private readonly RegionsCategoriesRepository $regionsCategoriesRepository,
        private readonly CitiesCategoriesRepository $citiesCategoriesRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly CategoriesRepository $categoriesRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Categories $categories): FormReturn
    {
        return $this->process($categories);
    }

    protected function formType(): string
    {
        return CategoriesType::class;
    }

    protected function formName(): string
    {
        return 'categories';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("categorie_edited_success", [], 'modules');
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
        $form->get('name')->setData($entity->getName());
        $form->get('description')->setData($entity->getDescription());
        $form->get('activated')->setData($entity->isActive());

        return $form;
    }

    protected function edit(array $data, object $entity): void
    {

        $image = $this->request->getCurrentRequest()->files->get("categories", [])["image"] ?? [];
        if (!empty($image)) {
            $dir = $this->parameters->get("upload_products") . $this->getDomainData->getTenantCache()->getId() . '/' . $this->security->getUser()->getId() . '/';
            $data["image"] = $this->imageUtils->addImage(dir: $dir, file: $image);
        }

        $argument = new CategoriesArgument($data);
        $entity->edit($argument);
        $activated = $data["activated"] ?? $entity->isActive();
        $activated ? $entity->activate() : $entity->deactivate();

        $this->syncCountries($entity, array_filter((array)($data['countries'] ?? [])));
        $this->syncRegions($entity, array_filter((array)($data['regions'] ?? [])));
        $this->syncCities($entity, array_filter((array)($data['cities'] ?? [])));

        $this->countriesCategoriesRepository->invalidateSelectedIdsCache($entity);
        $this->regionsCategoriesRepository->invalidateSelectedIdsCache($entity);
        $this->citiesCategoriesRepository->invalidateSelectedIdsCache($entity);

        $this->customeEntityManager->add($entity, true);

        $this->categoriesRepository->invalidatePublicCache($entity->getDomain());
    }

    private function syncCountries(Categories $entity, array $incomingIds): void
    {
        $existing = $this->countriesCategoriesRepository->findBy(['categories' => $entity]);
        $existingById = [];
        foreach ($existing as $link) {
            $existingById[(string)$link->getCountry()->getId()] = $link;
        }
        foreach ($incomingIds as $id) {
            if (isset($existingById[$id])) {
                $existingById[$id]->activate();
            } else {
                $country = $this->countriesRepository->find($id);
                if ($country) {
                    $this->customeEntityManager->add((new CountriesCategories())->add($country, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
            }
        }
    }

    private function syncRegions(Categories $entity, array $incomingIds): void
    {
        $existing = $this->regionsCategoriesRepository->findBy(['categories' => $entity]);
        $existingById = [];
        foreach ($existing as $link) {
            $existingById[(string)$link->getRegion()->getId()] = $link;
        }
        foreach ($incomingIds as $id) {
            if (isset($existingById[$id])) {
                $existingById[$id]->activate();
            } else {
                $region = $this->regionsRepository->find($id);
                if ($region) {
                    $this->customeEntityManager->add((new RegionsCategories())->add($region, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
            }
        }
    }

    private function syncCities(Categories $entity, array $incomingIds): void
    {
        $existing = $this->citiesCategoriesRepository->findBy(['categories' => $entity]);
        $existingById = [];
        foreach ($existing as $link) {
            $existingById[(string)$link->getCity()->getId()] = $link;
        }
        foreach ($incomingIds as $id) {
            if (isset($existingById[$id])) {
                $existingById[$id]->activate();
            } else {
                $city = $this->citiesRepository->find($id);
                if ($city) {
                    $this->customeEntityManager->add((new CitiesCategories())->add($city, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
            }
        }
    }
}
