<?php

namespace App\Handler\UseCase\Statistics;

use App\Entity\Tenants\Statistics\StatisticsEvents;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Message\Statistics\RecordStatisticsEventMessage;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Users\UsersRepository;
use App\Util\UUIDUtil;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordStatisticsEventHandler
{
    public function __construct(
        private DomainsRepository             $domainsRepository,
        private UsersRepository               $usersRepository,
        private CustomeEntityManagerInterface $entityManager,
        private LogInterface                  $log,
    ) {}

    public function __invoke(RecordStatisticsEventMessage $message): void
    {
        try {
            $domain = $this->domainsRepository->find(
                UUIDUtil::convertIdToSearch($message->domainId)
            );

            if ($domain === null) {
                return;
            }

            $user = $message->userId !== null
                ? $this->usersRepository->find(UUIDUtil::convertIdToSearch($message->userId))
                : null;

            $event = (new StatisticsEvents())->add(
                domain: $domain,
                eventName: $message->eventName,
                eventTarget: $message->eventTarget,
                page: $message->page,
                metadata: $message->metadata,
                sessionId: $message->sessionId,
                user: $user,
            );

            $this->entityManager->add($event, true);
        } catch (\Throwable $th) {
            $this->log->handler($th);
        }
    }
}
