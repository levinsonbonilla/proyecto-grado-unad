<?php

namespace App\Service\Products;

final readonly class VariantLabelFormatterService implements VariantLabelFormatterInterface
{
    public function format(?string $colorName, ?string $medidaName): string
    {
        $parts = array_filter([$colorName, $medidaName], fn (?string $v) => !empty($v));
        if (empty($parts)) {
            return '';
        }

        return ' (' . implode(', ', $parts) . ')';
    }
}
