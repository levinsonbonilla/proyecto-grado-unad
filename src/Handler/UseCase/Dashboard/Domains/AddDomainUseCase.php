<?php

namespace App\Handler\UseCase\Dashboard\Domains;

use App\ArgumentHandler\DomainsArgument;
use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Configurations\Cities\CitiesDomains;
use App\Entity\Configurations\Countries\CountriesDomains;
use App\Entity\Configurations\Regions\RegionsDomains;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Exception\GenericException;
use App\Form\Dashboard\DomainsType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Dashboard\Domains\AddDomainInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddDomainUseCase extends AbstractAddHandler implements AddDomainInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly DomainsRepository $domainsRepository,
        private readonly S3ManagerInterface $s3Manager,
        private readonly ParameterBagInterface $parameters,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly Security $security,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(?Tenants $targetTenant = null): FormReturn
    {
        return $this->process(
            DomainsType::class,
            $targetTenant !== null ? ['targetTenant' => $targetTenant] : null
        );
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("domain_created_successfully", [], 'domains');
    }

    protected function rollbackOnError(): void
    {
        if (!empty($this->s3Image)) {
            $this->s3Manager->delete($this->s3Image);
        }
    }

    protected function add(array $data, array $additionalData): void
    {

        $file = $additionalData['image'];

        $s3Image = $this->s3Manager->create($file, $this->parameters->get("upload_domains"));
        if (empty($s3Image)) {
            throw new \Exception("Ocurrió un error durante la creación de una imagen en CDN, revisar el error anterior", 500);
        }

        $this->s3Image = $s3Image;
        $data["logo"] = $s3Image;

        $targetTenant = $additionalData['targetTenant'] ?? $this->getDomainData->getTenant();

        $argument = new DomainsArgument($data, $targetTenant);

        $domain = $this->domainsRepository->findOneBy(["domain" => $argument->getDomain()]);
        if (!empty($domain)) {
            throw new GenericException($this->translator->trans("existing_domain", [], "domains"), 400);
        }

        $domain = new Domains();
        $domain->add($argument);
        $this->customeEntityManager->add($domain, true);
        $this->domainsRepository->invalidateCacheDomain($domain->getDomain());

        $this->domainsRepository->invalidateActiveDomainsByTenantCache($targetTenant);
        $this->autoProvisionAccess($domain, $targetTenant);

        foreach (array_filter((array)($data['countries'] ?? [])) as $id) {
            $country = $this->countriesRepository->find($id);
            if ($country) {
                $this->customeEntityManager->add((new CountriesDomains())->add($country, $domain), false);
            }
        }
        foreach (array_filter((array)($data['regions'] ?? [])) as $id) {
            $region = $this->regionsRepository->find($id);
            if ($region) {
                $this->customeEntityManager->add((new RegionsDomains())->add($region, $domain), false);
            }
        }
        foreach (array_filter((array)($data['cities'] ?? [])) as $id) {
            $city = $this->citiesRepository->find($id);
            if ($city) {
                $this->customeEntityManager->add((new CitiesDomains())->add($city, $domain), false);
            }
        }

        $this->customeEntityManager->flush();
    }

    private function autoProvisionAccess(Domains $domain, Tenants $targetTenant): void
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof Users || in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true)) {
            return;
        }

        $usersDomains = new UsersDomains();
        $usersDomains->add(new UsersDomainsArgument([
            'user' => $currentUser,
            'domain' => $domain,
            'roles' => $this->resolveExistingRolesInTenant($currentUser, $targetTenant),
        ]));
        $this->customeEntityManager->add($usersDomains, false);
    }

    private function resolveExistingRolesInTenant(Users $user, Tenants $targetTenant): array
    {
        foreach ($user->getUserDomainsActives() as $usersDomains) {
            if ((string) $usersDomains->getDomain()->getTenant()->getId() === (string) $targetTenant->getId()) {
                return $usersDomains->getRoles() ?: ['ROLE_ADMIN'];
            }
        }

        return ['ROLE_ADMIN'];
    }
}
