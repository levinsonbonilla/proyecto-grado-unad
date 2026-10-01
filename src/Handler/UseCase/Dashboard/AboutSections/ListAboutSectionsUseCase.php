<?php

namespace App\Handler\UseCase\Dashboard\AboutSections;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Dashboard\AboutSections\ListAboutSectionsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\AboutSectionsRepository;
use App\Util\ComplementDataTable;

final class ListAboutSectionsUseCase implements ListAboutSectionsInterface
{
    public function __construct(
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
        private readonly AboutSectionsRepository $aboutSectionsRepository,
        private readonly GetDomainDataInterface $getDomainData
    ) {
    }

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $totalElements = $this->aboutSectionsRepository->getList(domain: $domain, isCount: true);
            $elements = $this->aboutSectionsRepository->getList(domain: $domain, isCount: false);

            return ComplementDataTable::returnListDataTable(
                dataTable: $this->listDataTable,
                totalElements: $totalElements,
                elements: $elements
            );
        } catch (\Throwable $th) {
            $this->log->handler(throwable: $th);
            return ComplementDataTable::returnListDataTable(
                dataTable: $this->listDataTable
            );
        }
    }
}
