<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Messages\SentMessagesInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessagesRepository;
use App\Util\ComplementDataTable;
use Symfony\Bundle\SecurityBundle\Security;

final class SentMessagesUseCase implements SentMessagesInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly HelpMessagesRepository $repository,
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {

            $user  = $this->security->getUser();
            $total = $this->repository->getSent(user: $user, isCount: true);
            $items = $this->repository->getSent(user: $user, isCount: false);
            return ComplementDataTable::returnListDataTable($this->listDataTable, $total, $items);
        } catch (\Throwable $th) {
            $this->log->handler(throwable: $th);
            return ComplementDataTable::returnListDataTable(dataTable: $this->listDataTable);
        }
    }
}
