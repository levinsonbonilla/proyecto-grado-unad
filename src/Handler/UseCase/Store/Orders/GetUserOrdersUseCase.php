<?php

namespace App\Handler\UseCase\Store\Orders;

use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Orders\GetUserOrdersInterface;
use App\Repository\Products\Orders\OrdersRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class GetUserOrdersUseCase implements GetUserOrdersInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OrdersRepository $ordersRepository,
        private readonly LogInterface $log,
        private readonly RequestStack $requestStack,
    ) {}

    public function handler(): array
    {
        try {
            $user = $this->security->getUser();
            if ($user === null) {
                return [];
            }
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';
            return $this->ordersRepository->findByUser($user, $locale);
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return [];
        }
    }
}
