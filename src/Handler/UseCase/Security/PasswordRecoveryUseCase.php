<?php

namespace App\Handler\UseCase\Security;

use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Form\Security\PasswordRecoveryType;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Security\PasswordRecoveryInterface;
use App\Repository\Users\UsersRepository;
use App\ReturnHandler\PasswordRecoveryReturn;
use App\Util\StringUtil;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PasswordRecoveryUseCase implements PasswordRecoveryInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $request,
        private readonly TranslatorInterface $translator,
        private readonly LogInterface $log,
        private readonly UsersRepository $usersRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly CustomeEntityManagerInterface $customeEntityManager
    ) {
    }

    public function handler(string $userUuid): PasswordRecoveryReturn
    {
        $message = null;
        $isError = false;
        $isServerError = false;
        $user = null;

        try {
            $form = $this->formFactory->create(PasswordRecoveryType::class);
            $form->handleRequest($this->request->getCurrentRequest());
            $user = $this->getUser($userUuid);

            if ($form->isSubmitted()) {
                if ($form->isValid()) {
                    $this->passwordChange($form, $user);
                    $message = $this->translator->trans("password_change_successful", [], 'login');
                }
            } else {
                $form->get("email")->setData($user->getEmail());
            }
        } catch (GenericException $e) {
            $log = $this->log->handler($e);
            $message = $e->getMessage();
            $isError = true;
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            $message = $this->translator->trans("password_change_error_message", [], 'login') .
                ' ' . $log?->getShortReference();
            $isError = true;
            $isServerError = true;
        }

        $return = new PasswordRecoveryReturn(
            $form,
            $user,
            $message,
            $isError,
            $isServerError
        );

        return $return;
    }

    public function getUser(string $userUuid): Users
    {
        $user = $this->usersRepository->findOneBy([
            "id" => StringUtil::convertToUuid($userUuid),
            "active" => true,
            "validatedEmail" => true
        ]);
        if (empty($user)) {
            throw new GenericException(
                $this->translator->trans("restart_process_error_message", [], "login"),
                404
            );
        }

        $now = new \DateTimeImmutable();
        if ($user->getUpdatedAt() > $now->modify('-1 hour')) {
            throw new GenericException(
                $this->translator->trans("password_change_recently_message", [], "login"),
                400
            );
        }

        return $user;
    }

    public function passwordChange(FormInterface $form, Users $user): void
    {
        $user->passwordChange(
            $this->userPasswordHasher->hashPassword($user, $form->get("password")->getData())
        );
        $this->customeEntityManager->add($user, true);
    }
}
