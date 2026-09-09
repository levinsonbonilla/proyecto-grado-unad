<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Interface\UseCase\Messages\MessageNotificationInterface;
use App\Interface\UseCase\Security\LogInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MessageNotificationService implements MessageNotificationInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LogInterface $log,
        private readonly TranslatorInterface $translator,
        private readonly string $senderEmail,
        private readonly string $senderName,
    ) {
    }

    public function notify(Users $recipient, Users $sender, string $messageText): void
    {
        try {
            $preview = mb_strlen($messageText) > 120
                ? mb_substr($messageText, 0, 120) . '...'
                : $messageText;

            $subject = $this->translator->trans(
                'new_message_subject',
                ['%name%' => $sender->getFullName()],
                'messages'
            );

            $body = $this->translator->trans(
                'new_message_body',
                ['%senderName%' => $sender->getFullName(), '%preview%' => $preview],
                'messages'
            );

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to($recipient->getEmail())
                ->subject($subject)
                ->text($body);

            $this->mailer->send($email);
        } catch (\Throwable $th) {
            $this->log->handler($th);
        }
    }
}
