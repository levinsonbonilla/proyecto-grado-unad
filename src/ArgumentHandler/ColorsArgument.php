<?php

namespace App\ArgumentHandler;

use App\Entity\Tenants\Domains\Domains;
use App\Util\ArrayUtil;

final class ColorsArgument
{
    private string $name;
    private string $hexCode;

    public function __construct(
        array $data,
        private readonly ?Domains $domains = null
    ) {
        $requireKeys = ["name", "hexCode"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: " . json_encode($requireKeys)
                    . " se recibio: " . json_encode($data),
                400
            );
        }

        $this->name = $data["name"];
        $this->hexCode = $data["hexCode"];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHexCode(): string
    {
        return $this->hexCode;
    }

    public function getDomain(): Domains
    {
        return $this->domains;
    }
}
