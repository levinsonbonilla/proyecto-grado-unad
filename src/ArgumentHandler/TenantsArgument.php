<?php

namespace App\ArgumentHandler;

use App\Util\ArrayUtil;

final class TenantsArgument
{
    private readonly string $name;
    private readonly string $description;
    private ?string $nit = null;
    private ?string $phone = null;
    private ?string $prefix = null;
    public function __construct(
        array $data
    ) {
        $requireKeys = ["name", "description"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: ". json_encode($requireKeys) 
                ." se recibio: " . json_encode($data),
                400
            );
        }

        $this->name = $data["name"];
        $this->description = $data["description"];
        $this->nit = ArrayUtil::validateExistKey($data, "nit");
        $this->phone = ArrayUtil::validateExistKey($data, "phone");
        $this->prefix = ArrayUtil::validateExistKey($data, "prefix");
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getNit(): ?string
    {
        return $this->nit;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }
}
