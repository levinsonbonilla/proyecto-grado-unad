<?php

namespace App\Service\Products;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductStockService implements ProductStockInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function available(Products $product, ?ProductsColors $block = null): ?int
    {
        return $block !== null ? $block->getStock() : $product->getStock();
    }

    public function currentAvailable(Products $product, ?ProductsColors $block = null): ?int
    {
        return $block !== null
            ? $this->fetchStock('products_colors', $block->getId()->toBinary())
            : $this->fetchStock('products', $product->getId()->toBinary());
    }

    public function reserve(Products $product, ?ProductsColors $block, int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $connection = $this->em->getConnection();

        if ($block !== null) {
            $reserved = $connection->executeStatement(
                'UPDATE products_colors SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL AND stock >= ?',
                [$quantity, $block->getId()->toBinary(), $quantity]
            );
            if ($reserved === 0 && $this->fetchStock('products_colors', $block->getId()->toBinary()) !== null) {
                return false;
            }

            $connection->executeStatement(
                'UPDATE products SET stock = CASE WHEN stock >= ? THEN stock - ? ELSE 0 END WHERE id = ? AND stock IS NOT NULL',
                [$quantity, $quantity, $product->getId()->toBinary()]
            );

            return true;
        }

        $reserved = $connection->executeStatement(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL AND stock >= ?',
            [$quantity, $product->getId()->toBinary(), $quantity]
        );

        return $reserved > 0 || $this->fetchStock('products', $product->getId()->toBinary()) === null;
    }

    public function release(Products $product, ?ProductsColors $block, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $connection = $this->em->getConnection();

        if ($block !== null) {
            $connection->executeStatement(
                'UPDATE products_colors SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL',
                [$quantity, $block->getId()->toBinary()]
            );
        }

        $connection->executeStatement(
            'UPDATE products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL',
            [$quantity, $product->getId()->toBinary()]
        );
    }

    private function fetchStock(string $table, string $binaryId): ?int
    {
        $value = $this->em->getConnection()->fetchOne("SELECT stock FROM {$table} WHERE id = ?", [$binaryId]);

        return $value === false || $value === null ? null : (int) $value;
    }
}
