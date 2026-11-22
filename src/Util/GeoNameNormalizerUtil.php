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

    public static function primaryKey(string $json): ?string
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $key = self::key($json);
            return $key === '' ? null : $key;
        }

        $names = isset($decoded['names']) && is_array($decoded['names']) ? $decoded['names'] : $decoded;
        foreach (['es', 'en'] as $language) {
            if (isset($names[$language]) && is_string($names[$language]) && $names[$language] !== '') {
                return self::key($names[$language]);
            }
        }

        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                return self::key($name);
            }
        }

        return null;
    }

    public static function sameName(string $jsonA, string $jsonB): bool
    {
        $a = self::primaryKey($jsonA);

        return $a !== null && $a === self::primaryKey($jsonB);
    }

    public static function stripAdministrativeSuffix(string $name): string
    {
        return trim(preg_replace('/\s+Department$/u', '', $name) ?? $name);
    }
}
