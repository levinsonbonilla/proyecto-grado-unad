<?php

namespace App\Handler\Configuration;

use App\Interface\Configuration\SecurityProcessInterface;
use App\Interface\UseCase\Security\LogInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SecurityProcessHandler implements SecurityProcessInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGeneratorInterface,
        private readonly LogInterface $log,
        private readonly ParameterBagInterface $params,
        private readonly RequestStack $requestStack
    ) {
    }

    #[\Override]
    public function redirectLogin(): ?RedirectResponse
    {
        if ($this->security->isGranted('IS_AUTHENTICATED_FULLY')) {

            if ($this->security->isGranted('ROLE_SUPER_ADMIN') || $this->security->isGranted('ROLE_ADMIN')) {

                $url = $this->urlGeneratorInterface->generate('dashboard', [
                    '_locale' => $this->currentLocale(),
                ]);

                $this->log->deleteLogsFile($this->params->get("number_of_days_of_log_permanence"));
            } else {
                $url = $this->urlGeneratorInterface->generate('store', [
                    '_locale' => $this->currentLocale(),
                ]);
            }

            return new RedirectResponse($url);
        }

        return null;
    }

    #[\Override]
    public function RedirectLogout(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urlGeneratorInterface->generate('store', [
                '_locale' => $this->currentLocale(),
            ])
        );
    }

    private function currentLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';
    }
}
