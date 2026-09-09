<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Messages\InboxMessagesInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessagesRepository;
use App\Util\ComplementDataTable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class InboxMessagesUseCase implements InboxMessagesInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly HelpMessagesRepository $repository,
        private readonly ListDataTableInterface $listDataTable,
        private readonly LogInterface $log,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function handler(): array
    {
        try {

            $user    = $this->security->getUser();
            $request = $this->requestStack->getCurrentRequest();

            $filterUserId = $request?->get('filterUserId') ?: null;
            $filterIsReadRaw = $request?->get('filterIsRead');
            $filterIsRead = $filterIsReadRaw !== null && $filterIsReadRaw !== ''
                ? (bool) $filterIsReadRaw
                : null;

            $total = $this->repository->getInbox(user: $user, isCount: true, filterUserId: $filterUserId, filterIsRead: $filterIsRead);
            $items = $this->repository->getInbox(user: $user, isCount: false, filterUserId: $filterUserId, filterIsRead: $filterIsRead);
            return ComplementDataTable::returnListDataTable($this->listDataTable, $total, $items);
        } catch (\Throwable $th) {
            $this->log->handler(throwable: $th);
            return ComplementDataTable::returnListDataTable(dataTable: $this->listDataTable);
        }
    }
}
