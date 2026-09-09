<?php

namespace App\Handler\UseCase\Dashboard\Domains;

use App\ArgumentHandler\DomainsArgument;
use App\Entity\Configurations\Cities\CitiesDomains;
use App\Entity\Configurations\Countries\CountriesDomains;
use App\Entity\Configurations\Regions\RegionsDomains;
use App\Entity\Tenants\Domains\Domains;
use App\Form\Dashboard\DomainsType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Dashboard\Domains\EditDomainInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Cities\CitiesDomainsRepository;
use App\Repository\Configurations\Countries\CountriesDomainsRepository;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Configurations\Regions\RegionsDomainsRepository;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditDomainUseCase extends AbstractEditHandler implements EditDomainInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly DomainsRepository $domainsRepository,
        private readonly S3ManagerInterface $s3Manager,
        private readonly ParameterBagInterface $parameters,
        private readonly CountriesDomainsRepository $countriesDomainsRepository,
        private readonly RegionsDomainsRepository $regionsDomainsRepository,
        private readonly CitiesDomainsRepository $citiesDomainsRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Domains $domain): FormReturn
    {
        return $this->process($domain);
    }

    protected function formType(): string
    {
        return DomainsType::class;
    }

    protected function formName(): string
    {
        return 'domains';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("domain_edited_success", [], 'domains');
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
        $form->get('domain')->setData($entity->getDomain());
        $form->get('name')->setData($entity->getName());
        $form->get('notificationEmail')->setData($entity->getNotificationEmail());
        $form->get('supportEmail')->setData($entity->getSupportEmail());
        $form->get('facebookUrl')->setData($entity->getFacebookUrl());
        $form->get('instagramUrl')->setData($entity->getInstagramUrl());
        $form->get('pinterestUrl')->setData($entity->getPinterestUrl());

        return $form;
    }

    protected function edit(array $data, object $entity): void
    {

        $imageFiles = $this->request->getCurrentRequest()->files->get('domains', []);

        $file = !empty($imageFiles) ? reset($imageFiles) : null;

        if (!empty($file) && !strpos($entity->getLogo(), $file->getRealPath())) {
            $s3Image = $this->s3Manager->create($file, $this->parameters->get("upload_domains"));
            $this->s3Manager->delete($entity->getLogo());
        } else {
            $s3Image = $entity->getLogo();
        }

        if (empty($s3Image)) {
            throw new \Exception("Ocurrió un error durante la creación de una imagen en CDN, revisar el error anterior", 500);
        }

        $data["logo"] = $s3Image;
        $argument = new DomainsArgument($data, $entity->getTenant());
        $entity->edit($argument);
        $data["activated"] ? $entity->activate() : $entity->deactivate();

        $this->syncCountries($entity, array_filter((array)($data['countries'] ?? [])));
        $this->syncRegions($entity, array_filter((array)($data['regions'] ?? [])));
        $this->syncCities($entity, array_filter((array)($data['cities'] ?? [])));

        $this->countriesDomainsRepository->invalidateSelectedIdsCache($entity);
        $this->regionsDomainsRepository->invalidateSelectedIdsCache($entity);
        $this->citiesDomainsRepository->invalidateSelectedIdsCache($entity);

        $this->customeEntityManager->add($entity, true);
        $this->domainsRepository->invalidateCacheDomain($entity->getDomain());

        $this->domainsRepository->invalidateActiveDomainsByTenantCache($entity->getTenant());
    }

    private function syncCountries(Domains $entity, array $incomingIds): void
    {
        $existing = $this->countriesDomainsRepository->findBy(['domain' => $entity]);
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
                    $this->customeEntityManager->add((new CountriesDomains())->add($country, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
            }
        }
    }

    private function syncRegions(Domains $entity, array $incomingIds): void
    {
        $existing = $this->regionsDomainsRepository->findBy(['domain' => $entity]);
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
                    $this->customeEntityManager->add((new RegionsDomains())->add($region, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
            }
        }
    }

    private function syncCities(Domains $entity, array $incomingIds): void
    {
        $existing = $this->citiesDomainsRepository->findBy(['domain' => $entity]);
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
                    $this->customeEntityManager->add((new CitiesDomains())->add($city, $entity), false);
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
