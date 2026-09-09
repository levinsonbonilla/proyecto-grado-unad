<?php

namespace App\Handler\UseCase\Modules\Products\Categories;

use App\Entity\Products\Categories\Categories;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\Categories\ToggleStatusCategoriesInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Categories\CategoriesRepository;

final class ToggleStatusCategoriesUseCase implements ToggleStatusCategoriesInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly CategoriesRepository $categoriesRepository,
        private readonly LogInterface $log
    ) {
    }

    public function handler(Categories $category): array
    {
        try {
            $category->isActive() ? $category->deactivate() : $category->activate();
            $this->customeEntityManager->add($category, true);
            $this->categoriesRepository->invalidatePublicCache($category->getDomain());
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
