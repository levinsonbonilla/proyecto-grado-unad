<?php

namespace App\Util;

use Symfony\Component\Uid\Uuid;

final class StringUtil
{
    public const SEPARATOR = "-C0D";

    public static function removeHTTP(string $link): string
    {
        $search = [
            "http://",
            "https://"
        ];

        return trim(str_replace($search, '', trim($link)), "/");
    }

    public static function removeProhibitedCharactersForCache(string $name): string
    {
        $search = [
            '{',
            '}',
            '(',
            ')',
            '/',
            '\\',
            '@',
            ':',
            '"',
            '.'
        ];

        return trim(str_replace($search, '', $name));
    }

    public static function convertToUuid(string $uuidString): ?Uuid
    {
        if (!preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuidString
        )) {
            $uuidString = substr($uuidString, 0, 8) . '-' .
                substr($uuidString, 8, 4) . '-' .
                substr($uuidString, 12, 4) . '-' .
                substr($uuidString, 16, 4) . '-' .
                substr($uuidString, 20);
        }

        return Uuid::fromString($uuidString);
    }

    public static function changeUrlWithLang(string $url, string $lang): string
    {
       $url = rtrim($url, '/') . '/';

        $replaced = str_replace(["/en/", "/es/", "/br/"], "/$lang/", $url, $count);
        if ($count > 0) {
            return $replaced;
        }

        if (preg_match('#^(https?://[^/]+)(/.*)$#', $url, $matches)) {
            return $matches[1] . '/' . $lang . $matches[2];
        }

        return '/' . $lang . $url;
    }

    public static function generatePassword(): string
    {
        $length = 10;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
        $charactersLength = strlen($characters);
        $randomPassword = '';

        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomPassword;
    }
}
