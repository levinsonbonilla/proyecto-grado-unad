<?php

namespace App\Handler\Configuration;

use App\Exception\GenericException;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\MailerInterface as ConfigurationMailerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class MailerHandler implements ConfigurationMailerInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ParameterBagInterface $parameters,
        private readonly MailerInterface $mail,
        private readonly TransportInterface $transport,
        private readonly GetDomainDataInterface $getDomainData
    ) {
    }

    public function sendEmailGeneric(
        string $subject,
        string $to,
        string $template,
        array $data,
        ?array $repliesTo = [],
        ?array $bccs = []
    ): void {
        try {
            $message = (new Email());
            $message 
                ->from(
                    new Address(
                        $this->getDomainData->getDomainCache()->getNotificationEmail(), 
                        $this->getDomainData->getTenantCache()->getName())
                    )
                ->to($to)
                ->subject($subject)
                ->html($this->twig->render($template, $data));

            foreach ($repliesTo as $key => $replyTo) {
                $message->addReplyTo($replyTo);
            }

            foreach ($bccs as $key => $bcc) {
                $message->addBcc($bcc);
            }

            $this->transport->send($message);
        } catch (TransportExceptionInterface $e) {
            throw new GenericException($e->getMessage(), $e->getCode());
            
        }
    }
}
