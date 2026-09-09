<?php

namespace App\ArgumentHandler;

use App\Entity\Tenants\Domains\Domains;
use App\Trait\Argument\BaseArgument;
use App\Util\ArrayUtil;

final class SlidesArgument
{
    use BaseArgument;
    private ?string $name = null;
    private ?string $description = null;
    private string $image;
    private string $type;
    private int $position;

    public function __construct(
        array $data,
        private Domains $domains
    ) {
        $requireKeys = ["type", "position"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: ". json_encode($requireKeys)
                ." se recibio: " . json_encode($data),
                400
            );
        }

        $this->name = ArrayUtil::validateExistKey($data, "name");
        $this->description = ArrayUtil::validateExistKey($data, "description");
        $this->image = ArrayUtil::validateExistKey($data, "image");
        $this->type = ArrayUtil::validateExistKey($data, "type");
        $this->position = (int)ArrayUtil::validateExistKey($data, "position");
    }
}
