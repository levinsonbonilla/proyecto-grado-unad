<?php

namespace App\Handler\UseCase\Modules\Products\Medidas;

use App\Exception\GenericException;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Modules\Products\Medidas\ListMedidasInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Medidas\MedidasRepository;

final class ListMedidasUseCase implements ListMedidasInterface
{
    public function __construct(
        private readonly MedidasRepository $medidasRepository,
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {
            $elements = $this->medidasRepository->getMedidasList();
            $totalElements = $this->medidasRepository->getMedidasList(true);

            $data = [
                "draw" => $this->listDataTable->getDraw(),
                "recordsTotal" => reset($totalElements),
                "recordsFiltered" => reset($totalElements),
                "data" => $elements
            ];
        } catch (GenericException $e) {
            $this->log->handler($e);
            $data = ["draw" => 1, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            $data = ["draw" => 1, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []];
        }

        return $data;
    }
}
