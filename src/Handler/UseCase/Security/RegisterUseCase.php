<?php

namespace App\Handler\UseCase\Security;

use App\ArgumentHandler\DomainsArgument;
use App\ArgumentHandler\TenantsArgument;
use App\ArgumentHandler\UsersArgument;
use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Exception\GenericException;
use App\Form\Security\RegisterType;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\LocationInterface;
use App\Interface\Configuration\MailerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Security\RegisterInterface;
use App\Repository\Users\UsersDomainsRepository;
use App\Repository\Users\UsersRepository;
use App\ReturnHandler\FormReturn;
use App\Util\ArrayUtil;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RegisterUseCase implements RegisterInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly UsersRepository $usersRepository,
        private readonly LogInterface $log,
        private readonly RequestStack $request,
        private readonly TranslatorInterface $translator,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LocationInterface $location,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface  $userPasswordHasher,
        private readonly UrlGeneratorInterface  $urlGenerator,
        private readonly UsersDomainsRepository $usersDomainsRepository,
        private readonly CustomeEntityManagerInterface $customeEntityManager
    ) {
    }

    public function handler(?array $data = []): FormReturn
    {
        $registerForm = $this->formFactory->create(RegisterType::class);
        $message = null;
        $isError = false;
        $isProcess = false;
        $isValidatedEmail = false;
        $registered = false;
        $isServerError = false;
        try {
            $registerForm->handleRequest($this->request->getCurrentRequest());
            if ($registerForm->isSubmitted()) {
                if ($registerForm->isValid()) {
                    $user = $this->addUserProcess($this->request->getCurrentRequest()->get("register", []));
                    $isValidatedEmail = $user->isValidatedEmail();
                    $registered = true;
                }

                $isProcess = true;
            } elseif (count($data) > 0) {
                $user = $this->addUserProcess($data);
                $isValidatedEmail = $user->isValidatedEmail();
                $registered = true;
                $isProcess = true;
            }

            if ($registered) {
                $message = $this->translator->trans("registration_success_message", [], 'login');
                if ($isValidatedEmail) {
                    $message = $this->translator->trans("user_registration_success_to_login", [], 'login');
                }
            } elseif ($isProcess) {
                $message = $this->translator->trans("registration_form_invalid_message", [], 'login');
                $isError = true;
            }
        } catch (GenericException $e) {
            $log = $this->log->handler($e);
            $message = $e->getMessage();
            $isError = true;
            $isProcess = true;
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            $message = $this->translator->trans("registration_error_with_id_message", [], 'login') .
                ' ' . $log?->getShortReference();
            $isError = true;
            $isProcess = true;
            $isServerError = true;
        }

        $return = new FormReturn(
            $registerForm,
            $message,
            $isError,
            $isProcess,
            $isServerError
        );

        return $return;
    }

    private function addUserProcess(array $data): Users
    {
        if ($this->getDomainData->getTenantCache()->isPrincipal()) {

            return $this->registerTenantOwner($data);
        }

        $user = $this->usersRepository->findOneBy(["email" => $data["email"]]);

        if (empty($user)) {
            $user = $this->addUser($data, $this->getDomainData->getDomain());
        } else {
            $this->passwordChange($user, $data);
            if ($user->getUsersDomainsByDomain($this->getDomainData->getDomainCache())) {
                throw new GenericException($this->translator->trans("existing_user", [], "login"), 1);
            }
        }

        $this->addUsersDomains(
            $user,
            $this->getDomainData->getDomain(),
            ['ROLE_USER']
        );
        if (!$user->isValidatedEmail()) {
            $this->sendEmail($user);
        }

        return $user;
    }

    private function registerTenantOwner(array $data): Users
    {
        $tenant = new Tenants();
        $tenant->add(new TenantsArgument([
            'name' => $data['businessName'] ?? '',
            'description' => $data['businessName'] ?? '',
            'nit' => '0',
            'phone' => $data['phone'] ?? null,
        ]));
        $this->customeEntityManager->add($tenant, false);

        $domain = new Domains();
        $domain->add(new DomainsArgument([
            'domain' => $this->translator->trans('new_tenant_domain_placeholder', [], 'login'),
            'logo' => '',
            'notificationEmail' => $data['email'] ?? '',
            'supportEmail' => $data['email'] ?? '',
        ], $tenant));
        $this->customeEntityManager->add($domain, false);

        $user = $this->usersRepository->findOneBy(['email' => $data['email'] ?? '']);
        if (empty($user)) {
            $user = $this->addUser($data, $domain);
        }

        $this->addUsersDomains($user, $domain, ['ROLE_ADMIN']);

        if (!$user->isValidatedEmail()) {
            $this->sendEmail($user);
        }

        return $user;
    }

    private function addUser(array $data, Domains $domain): Users
    {
        $argument = new UsersArgument(
            $data,
            $domain,
            $this->location->getCountry(),
            $this->location->getRegion(),
            $this->location->getCity()
        );
        $user = new Users($this->userPasswordHasher);
        $user->add($argument);
        $this->customeEntityManager->add($user);
        return $user;
    }

    private function passwordChange(Users $user, array $data): void
    {
        $requireKeys = ["password"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new GenericException(
                "ocurrio un error se esperaba: " . json_encode($requireKeys)
                    . " se recibio: " . json_encode($data),
                400
            );
        }
        $user->passwordChange(
            $this->userPasswordHasher->hashPassword($user, $data["password"])
        );
        $this->customeEntityManager->add($user, false);
    }

    private function addUsersDomains(Users $user, Domains $domain, array $roles): void
    {

        $data = [
            "user" => $user,
            "domain" => $domain,
            "roles" => $roles
        ];

        $usersDomainsArgument = new UsersDomainsArgument($data);
        $usersDomains = new UsersDomains();
        $usersDomains->add($usersDomainsArgument);
        $this->customeEntityManager->add($usersDomains, true);
    }

    private function sendEmail(Users $user): void
    {

        $locale = $this->request->getCurrentRequest()?->getLocale() ?? 'es';

        $subject = $this->translator->trans("confirm_email", [], "login");
        $template = "mailer/register.html.twig";
        $data = [
            'app_name' => $this->getDomainData->getTenantCache()->getName(),
            'user_name' => $user->getName() . " " . $user->getLastName(),
            'confirmation_link' => $this->request->getCurrentRequest()->getHttpHost() . $this->urlGenerator->generate(
                "security_confirmation",
                ["userUuid" => str_replace("-", "", $user->getId()->toString()), "_locale" => $locale]
            ),
            'support_email' => $this->getDomainData->getDomainCache()->getSupportEmail()
        ];

        $this->mailer->sendEmailGeneric(
            $subject,
            $user->getEmail(),
            $template,
            $data
        );
    }
}
