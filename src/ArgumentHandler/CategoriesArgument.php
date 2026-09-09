<?php

namespace App\ArgumentHandler;

use App\Entity\Tenants\Domains\Domains;
use App\Util\ArrayUtil;

final class CategoriesArgument
{
    private string $name;
    private string $description;
    private ?string $image = NULl;

    public function __construct(
        array $data,
        private readonly ?Domains $domains = null
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
        $this->image = ArrayUtil::validateExistKey($data, "image");
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDomain(): Domains
    {
        return $this->domains;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }
}
