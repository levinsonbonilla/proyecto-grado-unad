<?php

namespace App\Handler\UseCase\Modules\Sales\Orders;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Modules\Sales\Orders\ListSalesInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersRepository;
use App\Util\ComplementDataTable;
use Symfony\Component\HttpFoundation\RequestStack;

final class ListSalesUseCase implements ListSalesInterface
{
    public function __construct(
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
        private readonly OrdersRepository $ordersRepository,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $statusFilter = $this->requestStack->getCurrentRequest()?->get('status');

            $totalElements = $this->ordersRepository->getList(domain: $domain, isCount: true, statusFilter: $statusFilter);
            $elements = $this->ordersRepository->getList(domain: $domain, isCount: false, statusFilter: $statusFilter);

            return ComplementDataTable::returnListDataTable(
                dataTable: $this->listDataTable,
                totalElements: $totalElements,
                elements: $elements
            );
        } catch (\Throwable $th) {
            $this->log->handler(throwable: $th);
            return ComplementDataTable::returnListDataTable(dataTable: $this->listDataTable);
        }
    }
}
