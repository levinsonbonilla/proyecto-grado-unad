<?php

namespace App\Handler\UseCase\Security;

use App\Exception\GenericException;
use App\Form\Security\RecoveryType;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\MailerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Security\RecoveryInterface;
use App\Repository\Users\UsersRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RecoveryUseCase implements RecoveryInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $request,
        private readonly LogInterface $log,
        private readonly TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly UsersRepository $usersRepository,
        private readonly UrlGeneratorInterface  $urlGenerator
    ) {
    }

    public function handler(): FormInterface
    {
        $form = $this->formFactory->create(RecoveryType::class);
        try {
            $form->handleRequest($this->request->getCurrentRequest());
            if ($form->isSubmitted() && $form->isValid()) {
                $this->sendEmail($form);
            }
        } catch (GenericException $ge) {
            $this->log->handler($ge);
        } catch (\Throwable $th) {
            $this->log->handler($th);
        }

        return $form;
    }

    private function sendEmail(FormInterface $form): void
    {
        $user = $this->usersRepository->findOneBy([
            "email" => $form->get("email")->getData(),
            "active" => true,
            "validatedEmail" => true
        ]);
        if (empty($user)) {
            throw new GenericException(
                "Usuario inexistente, inactivo o no verificado pidiendo recuperar contraseña " . $form->get("email")->getData(),
                404
            );
        }

        $now = new \DateTimeImmutable();
        if ($user->getUpdatedAt() > $now->modify('-1 hour')) {
            throw new GenericException(
                "Usuario con cambio de contraseña reciente " . $form->get("email")->getData(),
                400
            );
        }

        $subject = $this->translator->trans("reset_access", [], "login");
        $template = "mailer/recovery.html.twig";
        $data = [
            'user_name' => $user->getName() . " " . $user->getLastName(),
            'reset_link' => $this->request->getCurrentRequest()->getHttpHost() . $this->urlGenerator->generate(
                "security_password_recovery",
                ["userUuid" => str_replace("-", "", $user->getId()->__tostring())]
            ),
            'app_name' => $this->getDomainData->getTenantCache()->getName(),
            'support_email' => $this->getDomainData->getDomainCache()->getSupportEmail(),
        ];

        $this->mailer->sendEmailGeneric(
            $subject,
            $user->getEmail(),
            $template,
            $data
        );
    }
}
