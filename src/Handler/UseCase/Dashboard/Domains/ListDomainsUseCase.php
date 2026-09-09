<?php

namespace App\Handler\UseCase\Dashboard\Domains;

use App\Exception\GenericException;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Dashboard\Domains\ListDomainsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class ListDomainsUseCase implements ListDomainsInterface
{

    public function __construct(
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
        private readonly DomainsRepository $domainsRepository,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly Security $security,
    ) {
    }

    public function handler(): array
    {
        try {

            $currentUser = $this->security->getUser();
            $isSuperAdmin = $currentUser !== null && in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true);
            $tenant = $isSuperAdmin ? null : $this->getDomainData->getTenantCache();

            $elements = $this->domainsRepository->getDomainsList($tenant);
            $totalElements = $this->domainsRepository->getDomainsList($tenant, true);

            $data = [
                "draw" => $this->listDataTable->getDraw(),
                "recordsTotal" => reset($totalElements),
                "recordsFiltered" => reset($totalElements),
                "data" => $elements
            ];
        } catch (GenericException $e) {
            $this->log->handler($e);
            $data = [
                "draw" => 1,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => []
            ];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            $data = [
                "draw" => 1,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => []
            ];
        }

        return $data;
    }
}
