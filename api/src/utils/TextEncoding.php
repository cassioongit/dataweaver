<?php

namespace Vogel\Utils;

class TextEncoding
{
    private const DBF_SOURCE_ENCODINGS = ['Windows-1252', 'ISO-8859-1'];

    public static function toUtf8($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (self::DBF_SOURCE_ENCODINGS as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        return $value;
    }

    public static function fromUtf8($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $value = self::toUtf8($value);

        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted === false || $converted === '') {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
        }

        return $converted !== false ? $converted : $value;
    }

    public static function normalizeForSearch($value)
    {
        $value = self::toUtf8((string) $value);
        $value = mb_strtolower($value, 'UTF-8');

        if (class_exists('\\Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false && $ascii !== '') {
            $value = $ascii;
        }

        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    public static function sanitizeImportValue($value, ?string $field = null, ?array &$report = null)
    {
        $report = [
            'changed' => false,
            'original' => $value,
            'value' => $value,
            'issues' => [],
            'field' => $field,
        ];

        if (!is_string($value) || $value === '') {
            return $value;
        }

        $working = trim($value);
        if ($working !== $value) {
            $report['issues'][] = 'trimmed';
        }

        $working = str_replace("\xC2\xA0", ' ', $working);

        if (!mb_check_encoding($working, 'UTF-8')) {
            $converted = self::fromLegacyEncodings($working);
            if ($converted !== $working) {
                $working = $converted;
                $report['issues'][] = 'reencoded_to_utf8';
            }
            if (!mb_check_encoding($working, 'UTF-8')) {
                $report['issues'][] = 'invalid_utf8_unfixed';
                $report['changed'] = $working !== $value;
                $report['value'] = $working;
                return $working;
            }
        }

        if (preg_match('/�/u', $working)) {
            $report['issues'][] = 'replacement_character_detected';
        } else {
            $mojibakeFixed = self::repairMojibake($working);
            if ($mojibakeFixed !== $working) {
                $working = $mojibakeFixed;
                $report['issues'][] = 'mojibake_repaired';
            }
        }

        $spacingFixed = self::fixInitialSpacing($working, $field);
        if ($spacingFixed !== $working) {
            $working = $spacingFixed;
            $report['issues'][] = 'spacing_normalized';
        }

        $working = preg_replace('/\s+/u', ' ', $working);
        $working = trim($working);

        if ($working !== $value) {
            $report['changed'] = true;
            $report['value'] = $working;
        }

        return $working;
    }

    private static function fromLegacyEncodings(string $value): string
    {
        foreach (self::DBF_SOURCE_ENCODINGS as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        return $value;
    }

    private static function repairMojibake(string $value): string
    {
        if ($value === '' || !preg_match('/[ÃÂ]/u', $value)) {
            return $value;
        }

        $candidate = @utf8_decode($value);
        if ($candidate === false || $candidate === '') {
            return $value;
        }

        if (!mb_check_encoding($candidate, 'UTF-8')) {
            return $value;
        }

        if (self::mojibakeScore($candidate) < self::mojibakeScore($value)) {
            return $candidate;
        }

        return $value;
    }

    private static function mojibakeScore(string $value): int
    {
        return preg_match_all('/[ÃÂ�]/u', $value, $matches);
    }

    private static function fixInitialSpacing(string $value, ?string $field = null): string
    {
        if ($value === '') {
            return $value;
        }

        $fieldsWithSpacingFix = ['nome', 'responsavel', 'responsável'];
        if ($field !== null && !in_array(mb_strtolower($field, 'UTF-8'), $fieldsWithSpacingFix, true)) {
            return $value;
        }

        $fixed = preg_replace('/(?<=\p{L})\.(?=\p{Lu})/u', '. ', $value);
        return $fixed !== null ? $fixed : $value;
    }
}
