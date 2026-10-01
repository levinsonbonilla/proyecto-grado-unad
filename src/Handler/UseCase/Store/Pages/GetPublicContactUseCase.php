<?php

namespace App\Handler\UseCase\Store\Pages;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Pages\GetPublicContactInterface;

final class GetPublicContactUseCase implements GetPublicContactInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            return [
                'address' => $domain->getContactAddress(),
                'phone' => $domain->getContactPhone(),
                'email' => $domain->getContactEmail(),
            ];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['address' => null, 'phone' => null, 'email' => null];
        }
    }
}
