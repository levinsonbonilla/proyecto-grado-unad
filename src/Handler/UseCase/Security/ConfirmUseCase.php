<?php

namespace App\Handler\UseCase\Security;

use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\ConfirmInterface;
use App\Repository\Users\UsersRepository;
use App\Util\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ConfirmUseCase implements ConfirmInterface
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager
    ) {
    }

    public function handler(string $userUuid): Users
    {
        $user = $this->usersRepository->findOneBy([
            "id" => StringUtil::convertToUuid($userUuid),
            "active" => true
        ]);
        if (empty($user)) {
            throw new GenericException($this->translator->trans("user_not_found", [], "login"), 400);
        }

        if ($user->isValidatedEmail()) {
            throw new GenericException($this->translator->trans("user_already_confirmed", [], "login"), 200);
        }

        $user->validateEmail();
        $this->customeEntityManager->add($user, true);
        return $user;
    }
}
