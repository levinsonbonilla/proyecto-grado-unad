<?php

namespace App\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\ListPaymentMethodsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use App\Util\ComplementDataTable;

final class ListPaymentMethodsUseCase implements ListPaymentMethodsInterface
{
    public function __construct(
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
        private readonly GetDomainDataInterface $getDomainData,
    ) {
    }

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $totalElements = $this->paymentMethodsRepository->getList(domain: $domain, isCount: true);
            $elements = $this->paymentMethodsRepository->getList(domain: $domain, isCount: false);
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
