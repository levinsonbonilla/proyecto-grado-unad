<?php
namespace App\Util;

final class UUIDUtil
{
    public static function convertBinaryToUuid($binaryUuid): string
    {
        if ($binaryUuid === null) {
            return '';
        }

        $hex = bin2hex($binaryUuid);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    public static function convertIdToSearch(object | string $object): string
    {
        if (is_object($object) && method_exists($object, 'getId')) {
            $stringUuid = str_replace('-', '', $object->getId());
        } else {
            $stringUuid = str_replace('-', '', $object);
        }
        return hex2bin($stringUuid);
    }
}
