<?php

namespace App\ArgumentHandler;

use App\Entity\Tenants\Domains\Domains;
use App\Trait\Argument\BaseArgument;
use App\Util\ArrayUtil;

final class ProductsArgument
{
    use BaseArgument;
    private readonly string $name;
    private readonly string $description;
    private readonly string $basePrice;
    private readonly string $publicPrice;
    private readonly ?int $stock;

    public function __construct(
        array $data,
        Private readonly Domains $domain
    ) {
        $requireKeys = ["name", "description", "basePrice", "publicPrice"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: " . json_encode($requireKeys)
                    . " se recibio: " . json_encode($data),
                400
            );
        }

        $this->name = $data["name"];
        $this->description = $data["description"];
        $this->basePrice = str_replace([".",","],"",$data["basePrice"]);
        $this->publicPrice = str_replace([".",","],"",$data["publicPrice"]);

        $stockRaw = ArrayUtil::validateExistKey($data, "stock");
        $this->stock = ($stockRaw !== null && trim($stockRaw) !== '') ? (int) $stockRaw : null;
    }
}
