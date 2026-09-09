<?php

namespace App\ArgumentHandler;

use App\Entity\Configurations\Globals\Countries;
use App\Util\ArrayUtil;

final class RegionsArgument
{
    private readonly string $name;
    private readonly string $description;
    private readonly string $isoCode;

    public function __construct(
        array $data,
        private readonly Countries $country
    ) {
        $requireKeys = ["name", "description", "isoCode"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: ". json_encode($requireKeys) 
                ." se recibio: " . json_encode($data),
                400
            );
        }

        $this->name = $data["name"];
        $this->description = $data["description"];
        $this->isoCode = $data["isoCode"];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCountry() : Countries
    {
        return $this->country;
    }

    public function getIsoCode(): string
    {
        return $this->isoCode;
    }
}
