<?php

namespace App\Handler\UseCase\Dashboard\Users;

use App\ArgumentHandler\UsersArgument;
use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Exception\GenericException;
use App\Form\Dashboard\UsersType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\MailerInterface;
use App\Interface\UseCase\Dashboard\Users\AddUserInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Users\UsersDomainsRepository;
use App\Repository\Users\UsersRepository;
use App\ReturnHandler\FormReturn;
use App\Util\StringUtil;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddUserUseCase extends AbstractAddHandler implements AddUserInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly UsersRepository $usersRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly UsersDomainsRepository $usersDomainsRepository,
        private readonly MailerInterface $mailer,
        private readonly DomainsRepository $domainsRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CustomeEntityManagerInterface $customeEntityManager
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(): FormReturn
    {
        return $this->process(UsersType::class);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("registration_successful", [], 'users');
    }

    protected function add(array $data, array $additionalData): void
    {
        $user = $this->usersRepository->findOneBy(["email" => $data["email"]]);
        if (!empty($user)) {
            throw new GenericException($this->translator->trans("existing_user", [], "users"), 400);
        }

        $data = $this->arrayRolesConvert($data);
        $data = $this->generatePassword($data);
        $domain = $this->getDomain($data);
        $user = $this->addUser($data, $domain);

        $this->addUsersDomains($user, $domain, $data["roles"]);

        $type = count($data["roles"]) > 0 ? "admin" : "user";
        $this->sendEmail($user, $domain, $type, $data["password"]);
    }

    private function addUser(array $data, Domains $domain): Users
    {
        $argument = new UsersArgument($data, $domain);
        $user = new Users($this->userPasswordHasher);
        $user->add($argument);
        $this->customeEntityManager->add($user);
        return $user;
    }

    private function addUsersDomains(Users $user, Domains $domain, array $roles): void
    {
        $data = ["user" => $user, "domain" => $domain, "roles" => $roles];
        $usersDomainsArgument = new UsersDomainsArgument($data);
        $usersDomains = new UsersDomains();
        $usersDomains->add($usersDomainsArgument);
        $this->customeEntityManager->add($usersDomains, true);
    }

    private function sendEmail(Users $user, Domains $domain, string $type, string $password): void
    {
        $subject = $this->translator->trans("confirm_email", [], "login");
        $template = "mailer/add_user.html.twig";
        $data = [
            'app_name' => $this->getDomainData->getTenantCache()->getName(),
            'user_name' => $user->getName() . " " . $user->getLastName(),
            'confirmation_link' => rtrim($domain->getDomain(), "/") . "/" . ltrim($this->urlGenerator->generate(
                "security_confirmation",
                ["userUuid" => str_replace("-", "", $user->getId()->toString())]
            ), "/"),
            'support_email' => $domain->getSupportEmail(),
            'type' => $type,
            'user' => $user->getEmail(),
            'password' => $password
        ];

        $this->mailer->sendEmailGeneric($subject, $user->getEmail(), $template, $data);
    }

    private function arrayRolesConvert(array $data): array
    {
        if (isset($data["roles"])) {
            $data["roles"] = is_array($data["roles"]) ? $data["roles"] : (array)$data["roles"];
        } else {
            $data["roles"] = [];
        }
        return $data;
    }

    private function getDomain(array $data): Domains
    {
        if (isset($data["domain"])) {
            $domain = $this->domainsRepository->find($data["domain"]);
            if (empty($domain)) {
                throw new GenericException($this->translator->trans("domain_not_found", [], "users"), 400);
            }
            return $domain;
        }
        return $this->getDomainData->getDomain();
    }

    private function generatePassword(array $data): array
    {
        $data["password"] = StringUtil::generatePassword();
        return $data;
    }
}
