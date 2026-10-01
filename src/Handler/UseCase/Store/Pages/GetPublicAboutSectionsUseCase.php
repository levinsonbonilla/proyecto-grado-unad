<?php

namespace App\Handler\UseCase\Store\Pages;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Pages\GetPublicAboutSectionsInterface;
use App\Repository\Tenants\Others\AboutSectionsRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GetPublicAboutSectionsUseCase implements GetPublicAboutSectionsInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly AboutSectionsRepository $aboutSectionsRepository,
        private readonly CacheInterface $cache,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            return $this->cache->get(
                'store_about_' . $domain->getId(),
                function (ItemInterface $item) use ($domain): array {
                    $item->expiresAfter(3600);
                    return $this->aboutSectionsRepository->getPublicActiveList($domain);
                }
            );
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return [];
        }
    }
}
