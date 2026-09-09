<?php

namespace App\Handler\UseCase\Dashboard\Slides;

use App\Entity\Tenants\Others\Slides;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Slides\DeleteSlideInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\SlidesRepository;

final class DeleteSlideUseCase implements DeleteSlideInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly SlidesRepository $slidesRepository,
        private readonly LogInterface $log
    ) {
    }

    public function handler(Slides $slide): array
    {
        try {
            $slide->isActive() ? $slide->deactivate() : $slide->activate();
            $this->customeEntityManager->add($slide, true);
            if ($slide->getDomain() !== null) {
                $this->slidesRepository->invalidatePublicCache($slide->getDomain());
            }
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
