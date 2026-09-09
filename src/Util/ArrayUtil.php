<?php

namespace App\Util;

final class ArrayUtil
{
    
    public static function validateKeys(array $requireKeys, array $baseArray): bool 
    {
        return count(array_intersect_key(array_flip($requireKeys), $baseArray)) === count($requireKeys);
    }

    public static function validateExistKey(array $array, string $key) : ?string
    {
        return $array[$key] ?? null;
    }    
}
