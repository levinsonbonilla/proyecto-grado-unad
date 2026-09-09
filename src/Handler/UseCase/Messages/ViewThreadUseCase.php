<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Messages\ViewThreadInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessageImagesRepository;
use App\Repository\Users\HelpMessagesRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class ViewThreadUseCase implements ViewThreadInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly HelpMessagesRepository $repository,
        private readonly HelpMessageImagesRepository $imagesRepository,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(HelpMessages $message): array
    {
        try {

            $currentUser = $this->security->getUser();

            if (!$message->isRead() && (string) $message->getToUser()->getId() === (string) $currentUser->getId()) {
                $message->markAsRead();
                $this->customeEntityManager->add($message, true);
            }

            $thread     = $this->repository->getThread($message);
            $thread     = array_map(function (array $msg): array {
                foreach ($msg as $k => $v) {
                    if ($v instanceof \Symfony\Component\Uid\AbstractUid) {
                        $msg[$k] = (string) $v;
                    }
                }
                return $msg;
            }, $thread);
            $messageIds = array_column($thread, 'id');
            $images     = $this->imagesRepository->getGroupedByMessageIds($messageIds);

            return ['success' => true, 'thread' => $thread, 'images' => $images, 'root' => $message];
        } catch (\Throwable $th) {
            $this->log->handler(throwable: $th);
            return ['success' => false];
        }
    }
}
