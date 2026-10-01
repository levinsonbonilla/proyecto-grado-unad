<?php

namespace App\ArgumentHandler;

use App\Entity\Tenants\Domains\Domains;
use App\Trait\Argument\BaseArgument;
use App\Util\ArrayUtil;

final class AboutSectionsArgument
{
    use BaseArgument;

    private string $title;
    private string $text;
    private ?string $image;
    private int $position;

    public function __construct(
        array $data,
        private Domains $domain,
        int $defaultPosition = 0
    ) {
        $requireKeys = ['title', 'text'];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                'ocurrio un error se esperaba: ' . json_encode($requireKeys)
                . ' se recibio: ' . json_encode($data),
                400
            );
        }

        $this->title = trim((string) $data['title']);
        $this->text = trim((string) $data['text']);
        $this->image = ArrayUtil::validateExistKey($data, 'image');
        $position = ArrayUtil::validateExistKey($data, 'position');
        $this->position = ($position === null || $position === '') ? $defaultPosition : (int) $position;
    }
}
