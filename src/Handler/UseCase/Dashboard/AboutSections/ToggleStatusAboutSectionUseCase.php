<?php

namespace App\Handler\UseCase\Dashboard\AboutSections;

use App\Entity\Tenants\Others\AboutSections;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\AboutSections\ToggleStatusAboutSectionInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\AboutSectionsRepository;

final class ToggleStatusAboutSectionUseCase implements ToggleStatusAboutSectionInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly AboutSectionsRepository $aboutSectionsRepository,
        private readonly LogInterface $log
    ) {
    }

    public function handler(AboutSections $section): array
    {
        try {
            $section->isActive() ? $section->deactivate() : $section->activate();
            $this->customeEntityManager->add($section, true);
            $this->aboutSectionsRepository->invalidatePublicCache($section->getDomain());
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
