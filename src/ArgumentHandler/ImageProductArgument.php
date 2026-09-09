<?php

namespace App\ArgumentHandler;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Trait\Argument\BaseArgument;
use App\Util\ArrayUtil;

final class ImageProductArgument
{
    use BaseArgument;
    private string $image;
    private ?string $description;
    private ?int $orderColumn;
    private ?ProductsColors $productColor;
    public function __construct(array $data, private readonly Products $product)
    {
        $requireKeys = ["image", "orderColumn"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: " . json_encode($requireKeys)
                    . " se recibio: " . json_encode($data),
                400
            );
        }

        $this->image = $data["image"];
        $this->description = $data["description"] ?? null;

        $orderColumnRaw = $data["orderColumn"] ?? null;
        $this->orderColumn = ($orderColumnRaw !== null && trim((string) $orderColumnRaw) !== '')
            ? (int) $orderColumnRaw
            : null;

        $this->productColor = $data["productColor"] ?? null;
    }

}
