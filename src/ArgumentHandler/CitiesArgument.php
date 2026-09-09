<?php

namespace App\ArgumentHandler;

use App\Entity\Configurations\Globals\Regions;
use App\Util\ArrayUtil;

final class CitiesArgument
{
    private readonly string $names;
    private readonly string $description;
    private readonly string $name;

    public function __construct(
        array $data,
        private readonly Regions $region 
    ) {
        $requireKeys = ["name", "description", "names"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: ". json_encode($requireKeys) 
                ." se recibio: " . json_encode($data),
                400
            );
        }

        $this->name = $data["name"];
        $this->description = $data["description"];
        $this->names = $data["names"];
    }

    public function getNames(): string
    {
        return $this->names;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getRegion(): Regions
    {
        return $this->region;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
