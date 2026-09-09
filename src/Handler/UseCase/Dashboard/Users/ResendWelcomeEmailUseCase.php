<?php

namespace App\Handler\UseCase\Dashboard\Users;

use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Interface\Configuration\MailerInterface;
use App\Interface\UseCase\Dashboard\Users\ResendWelcomeEmailInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use App\Util\StringUtil;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ResendWelcomeEmailUseCase implements ResendWelcomeEmailInterface
{
    public function __construct(
        private readonly LogInterface $log,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface  $urlGenerator,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function handler(Users $user): array
    {
        $isError = true;
        try {
            if ($user->isValidatedEmail()) {
                throw new GenericException($this->translator->trans("user_already_validated_email", [], 'users'), 400);
            }
            $this->sendEmail($user);
            $isError = false;
            $message = $this->translator->trans("registration_successful", [], 'users');
        } catch (GenericException $e) {
            $log = $this->log->handler($e);
            $message = $e->getMessage();
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            $message = $this->translator->trans("registration_error_with_id_message", [], 'login') .
                ' ' . $log?->getShortReference();
        }

        return ["isError" => $isError, "message" => $message];
    }

    private function sendEmail(Users $user): void
    {
        $type = count($user->getUsersDomainsActivesByDomain($user->getDomain())->getRoles()) > 0 ? "admin" : "user";
        $subject = $this->translator->trans("confirm_email", [], "login");
        $template = "mailer/add_user.html.twig";
        $data = [
            'app_name' => $user->getDomain()->getTenant()->getName(),
            'user_name' => $user->getName() . " " . $user->getLastName(),
            'confirmation_link' => rtrim($user->getDomain()->getDomain(), "/") . "/" . ltrim($this->urlGenerator->generate(
                "security_confirmation",
                ["userUuid" => str_replace("-", "", $user->getId()->toString())]
            ), "/"),
            'support_email' => $user->getDomain()->getSupportEmail(),
            'type' => $type,
            'user' => $user->getEmail(),
            'password' =>  StringUtil::generatePassword()
        ];

        $this->mailer->sendEmailGeneric(
            $subject,
            $user->getEmail(),
            $template,
            $data
        );
    }
}
