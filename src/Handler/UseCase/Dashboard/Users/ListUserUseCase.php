<?php

namespace App\Handler\UseCase\Dashboard\Users;

use App\Exception\GenericException;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Dashboard\Users\ListUserInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersDomainsRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class ListUserUseCase implements ListUserInterface
{

    public function __construct(
        private readonly UsersDomainsRepository $usersDomainsRepository,
        private readonly Security $security,
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {

            $elements = $this->usersDomainsRepository->getUserList();

            $totalElements = $this->usersDomainsRepository->getUserList(true);

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
