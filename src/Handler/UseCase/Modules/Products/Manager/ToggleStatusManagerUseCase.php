<?php

namespace App\Handler\UseCase\Modules\Products\Manager;

use App\Entity\Products\Products;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\Manager\ToggleStatusManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\ProductsRepository;

final class ToggleStatusManagerUseCase implements ToggleStatusManagerInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly ProductsRepository $productsRepository,
        private readonly LogInterface $log
    ) {
    }

    public function handler(Products $product): array
    {
        try {
            $product->isActive() ? $product->deactivate() : $product->activate();
            $this->customeEntityManager->add($product, true);
            $this->productsRepository->invalidatePublicHomeCache($product->getDomain());
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
