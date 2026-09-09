<?php

namespace App\Handler\UseCase\Dashboard\Slides;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Dashboard\Slides\ListSlidesInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\SlidesRepository;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\ComplementDataTable;

final class ListSlidesUseCase implements ListSlidesInterface
{
    public function __construct(
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
        private readonly SlidesRepository $slidesRepository,
        private readonly GetDomainDataInterface $getDomainData
    ) {
    }

    public function handler(): array
    {
        try {
            $totalElements = $this->slidesRepository->getList(domain: $this->getDomainData->getDomainCache(), isCount: true);
            $elements = $this->slidesRepository->getList(domain: $this->getDomainData->getDomainCache(), isCount: false);

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
