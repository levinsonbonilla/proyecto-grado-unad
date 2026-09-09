<?php

namespace App\Controller;

use App\Exception\GenericException;
use App\Interface\Configuration\SecurityProcessInterface;
use App\Interface\UseCase\Security\ConfirmInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Security\PasswordRecoveryInterface;
use App\Interface\UseCase\Security\RecoveryInterface;
use App\Interface\UseCase\Security\RegisterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '{_locale<%supported_locales%>}/', name: 'security')]
class SecurityController extends AbstractController
{

    public function __construct(
        private readonly LogInterface $log,
        private readonly TranslatorInterface $translator
    ) {
    }

    #[Route(path: 'login', name: '_login')]
    #[Route(path: 'register', name: '_register')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        SecurityProcessInterface $securityProcess,
        RegisterInterface $register
    ): Response {

        if ($this->isGranted("IS_AUTHENTICATED_FULLY")) {
            return $securityProcess->redirectLogin();
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error) {
            $this->addFlash(
                "error",
                $this->translator->trans($error->getMessageKey(), $error->getMessageData(), 'security')
            );
        }

        $register = $register->handler();
        if ($register->isProcess()) {
            $this->addFlash($register->getFlashType(), $register->getMessage());
        }

        return $this->render('security/login.html.twig', [
            'form' => $register->getForm()
        ]);
    }

    #[Route(path: 'logout', name: '_logout')]
    public function logout(SecurityProcessInterface $securityProcess): Response
    {
        return $securityProcess->RedirectLogout();
    }

    #[Route(path: 'confirmation/{userUuid}', name: '_confirmation')]
    public function confirmation(
        string $userUuid,
        ConfirmInterface $confirmUseCase,
        Request $request
    ): Response {
        try {
            $user = $confirmUseCase->handler($userUuid);
        } catch (GenericException $ge) {
            $this->log->handler($ge);
            $this->addFlash("error", $ge->getMessage());
            return  $this->redirectToRoute("security_login");
        } catch (\Throwable $th) {
            $message = $this->translator->trans("registration_error_with_id_message", [], 'login') .
                ' ' . $this->log->handler($th)?->getShortReference();
            $this->addFlash("error", $message);
            return  $this->redirectToRoute("security_login");
        }

        $this->addFlash('success', $this->translator->trans('email_confirmed_login', [], 'login'));
        return $this->redirectToRoute('security_login', ['_locale' => $request->getLocale()]);
    }

    #[Route(path: 'recovery', name: '_recovery')]
    public function recovery(
        RecoveryInterface $recovery
    ): Response {
        $form = $recovery->handler();
        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash("success", $this->translator->trans("check_email_for_instructions", [], "login"));
        }
        return $this->render('security/recovery.html.twig', ["form" => $form]);
    }

    #[Route(path: 'password/recovery/{userUuid}', name: '_password_recovery')]
    public function passwordRecovery(
        string $userUuid,
        PasswordRecoveryInterface $passwordRecovery
    ): Response {
        $process = $passwordRecovery->handler($userUuid);
        if (empty($process->getUser())) {
            $this->addFlash("error", $process->getMessage());
            return  $this->redirectToRoute("security_login");
        }

        if (!empty($process->getMessage())) {
            $this->addFlash($process->getFlashType(), $process->getMessage());
        }

        return $this->render('security/password_recovery.html.twig', [
            "form" => $process->getForm(),
            "user" => $process->getUser()
        ]);
    }
}
