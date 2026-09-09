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
use App\Form\Security\RegisterTenantType;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\MailerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Security\RegisterTenantInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Users\UsersRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegisterTenantUseCase implements RegisterTenantInterface
{
    private const RESERVED_SLUGS = [
        'www', 'api', 'admin', 'dashboard', 'mail', 'ftp', 'static', 'cdn', 'app',
        'proyecto-grado-unad', 'assets', 'support', 'help', 'blog', 'status', 'docs',
    ];

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $request,
        private readonly TranslatorInterface $translator,
        private readonly UsersRepository $usersRepository,
        private readonly DomainsRepository $domainsRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LogInterface $log,
        private readonly string $platformBaseDomain,

        private readonly string $platformScheme = 'https',
    ) {
    }

    public function handler(?array $data = []): FormReturn
    {
        $registerForm = $this->formFactory->create(RegisterTenantType::class);
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
                    $user = $this->registerTenant($this->request->getCurrentRequest()->get('register_tenant', []));
                    $isValidatedEmail = $user->isValidatedEmail();
                    $registered = true;
                }
                $isProcess = true;
            } elseif (count($data) > 0) {
                $user = $this->registerTenant($data);
                $isValidatedEmail = $user->isValidatedEmail();
                $registered = true;
                $isProcess = true;
            }

            if ($registered) {
                $message = $this->translator->trans('tenant_registration_success_message', [], 'login');
                if ($isValidatedEmail) {
                    $message = $this->translator->trans('user_registration_success_to_login', [], 'login');
                }
            } elseif ($isProcess) {

                $message = $this->translator->trans('tenant_signup_form_invalid_message', [], 'login');
                $isError = true;
            }
        } catch (GenericException $e) {
            $this->log->handler($e);
            $message = $e->getMessage();
            $isError = true;
            $isProcess = true;
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            $message = $this->translator->trans('registration_error_with_id_message', [], 'login') .
                ' ' . $log?->getShortReference();
            $isError = true;
            $isProcess = true;
            $isServerError = true;
        }

        return new FormReturn($registerForm, $message, $isError, $isProcess, $isServerError);
    }

    private function registerTenant(array $data): Users
    {
        $slug = $this->validateSlug((string) ($data['slug'] ?? ''));
        $domainUrl = "{$this->platformScheme}://{$slug}.{$this->platformBaseDomain}";

        $tenant = new Tenants();
        $tenant->add(new TenantsArgument([
            'name' => $data['businessName'] ?? '',
            'description' => $data['businessName'] ?? '',
        ]));
        $this->customeEntityManager->add($tenant, false);

        $domain = new Domains();
        $domain->add(new DomainsArgument([
            'domain' => $domainUrl,
            'logo' => '',

            'notificationEmail' => $data['email'] ?? '',
            'supportEmail' => $data['email'] ?? '',
        ], $tenant));
        $this->customeEntityManager->add($domain, false);

        $user = $this->usersRepository->findOneBy(['email' => $data['email'] ?? '']);
        if ($user === null) {
            $argument = new UsersArgument($data, $domain, null, null, null);
            $user = new Users($this->userPasswordHasher);
            $user->add($argument);
            $this->customeEntityManager->add($user, false);
        }

        $usersDomains = new UsersDomains();
        $usersDomains->add(new UsersDomainsArgument([
            'user' => $user,
            'domain' => $domain,
            'roles' => ['ROLE_ADMIN'],
        ]));
        $this->customeEntityManager->add($usersDomains, true);

        if (!$user->isValidatedEmail()) {
            $this->sendVerificationEmail($user, $domain, $tenant);
        }

        return $user;
    }

    private function validateSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        if (!preg_match('/^[a-z0-9](-?[a-z0-9]+)*$/', $slug) || strlen($slug) < 3 || strlen($slug) > 40) {
            throw new GenericException($this->translator->trans('invalid_slug', [], 'login'), 400);
        }
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new GenericException($this->translator->trans('reserved_slug', [], 'login'), 400);
        }

        $candidateDomain = "{$this->platformScheme}://{$slug}.{$this->platformBaseDomain}";
        if ($this->domainsRepository->findOneBy(['domain' => $candidateDomain]) !== null) {
            throw new GenericException($this->translator->trans('slug_taken', [], 'login'), 400);
        }

        return $slug;
    }

    private function sendVerificationEmail(Users $user, Domains $domain, Tenants $tenant): void
    {

        $locale = $this->request->getCurrentRequest()?->getLocale() ?? 'es';

        $subject = $this->translator->trans('confirm_email', [], 'login');
        $template = 'mailer/register.html.twig';
        $requestData = [
            'app_name' => $tenant->getName(),
            'user_name' => $user->getName() . ' ' . $user->getLastName(),
            'confirmation_link' => $domain->getDomain() . $this->urlGenerator->generate(
                'security_confirmation',
                ['userUuid' => str_replace('-', '', $user->getId()->toString()), '_locale' => $locale]
            ),
            'support_email' => $domain->getSupportEmail(),
        ];

        $this->mailer->sendEmailGeneric($subject, $user->getEmail(), $template, $requestData);
    }
}
