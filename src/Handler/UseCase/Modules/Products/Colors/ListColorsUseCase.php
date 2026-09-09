<?php

namespace App\Handler\UseCase\Modules\Products\Colors;

use App\Exception\GenericException;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Modules\Products\Colors\ListColorsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Colors\ColorsRepository;

final class ListColorsUseCase implements ListColorsInterface
{
    public function __construct(
        private readonly ColorsRepository $colorsRepository,
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {
            $elements = $this->colorsRepository->getColorsList();
            $totalElements = $this->colorsRepository->getColorsList(true);

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
