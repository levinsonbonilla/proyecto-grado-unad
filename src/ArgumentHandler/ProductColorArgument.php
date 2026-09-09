<?php

namespace App\ArgumentHandler;

use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Products;
use App\Trait\Argument\BaseArgument;
use App\Util\ArrayUtil;

final class ProductColorArgument
{
    use BaseArgument;

    private ?Colors $color;
    private ?Medidas $medida;
    private ?int $stock;
    private ?string $image;
    private ?string $variantGroupId;
    private ?int $orderColumn;

    public function __construct(
        array $data,
        private readonly Products $product,
    ) {

        $this->color = $data["color"] ?? null;
        $this->medida = $data["medida"] ?? null;

        $stockRaw = ArrayUtil::validateExistKey($data, "stock");
        $this->stock = ($stockRaw !== null && trim((string) $stockRaw) !== '') ? (int) $stockRaw : null;

        $this->image = $data["image"] ?? null;

        $this->variantGroupId = $data["variantGroupId"] ?? null;

        $orderColumnRaw = $data["orderColumn"] ?? null;
        $this->orderColumn = ($orderColumnRaw !== null && trim((string) $orderColumnRaw) !== '')
            ? (int) $orderColumnRaw
            : null;
    }

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function getColor(): ?Colors
    {
        return $this->color;
    }

    public function getMedida(): ?Medidas
    {
        return $this->medida;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getVariantGroupId(): ?string
    {
        return $this->variantGroupId;
    }

    public function getOrderColumn(): ?int
    {
        return $this->orderColumn;
    }
}
