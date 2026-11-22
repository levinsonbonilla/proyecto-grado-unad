<?php

namespace App\Util;

final class GeoNameNormalizerUtil
{
    private const NOISE_PATTERNS = [
        '/\bdepartment\b/u',
        '/^departamento (del |de la |de )?/u',
        '/^department of /u',
        '/\bprovince\b/u',
        '/^provincia (del |de la |de )?/u',
    ];

    public static function key(string $name): string
    {
        $value = mb_strtolower(trim($name));
        $ascii = \Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
        $value = preg_replace(self::NOISE_PATTERNS, '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public static function keysFromJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return array_values(array_filter([self::key($json)]));
        }

        $names = isset($decoded['names']) && is_array($decoded['names']) ? $decoded['names'] : $decoded;
        $keys = [];
        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                $keys[] = self::key($name);
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    public static function stripAdministrativeSuffix(string $name): string
    {
        return trim(preg_replace('/\s+Department$/u', '', $name) ?? $name);
    }
}
