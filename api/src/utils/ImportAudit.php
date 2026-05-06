<?php

namespace Vogel\Utils;

class ImportAudit
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    public static function writeReport(string $projectRoot, string $kind, array $payload): string
    {
        $auditDir = $projectRoot . '/logs/import_audits';
        if (!is_dir($auditDir) && !mkdir($auditDir, 0777, true) && !is_dir($auditDir)) {
            throw new \RuntimeException('Nao foi possivel criar o diretorio de auditoria.');
        }

        $sourceName = $payload['file_name'] ?? $payload['file'] ?? 'import';
        $timestamp = date('Ymd_His');
        $filename = sprintf(
            '%s_%s_%s.json',
            $kind,
            $timestamp,
            self::slug($sourceName)
        );

        $json = json_encode($payload, self::JSON_FLAGS);
        if ($json === false) {
            throw new \RuntimeException('Nao foi possivel serializar o relatorio de auditoria.');
        }

        $fullPath = $auditDir . '/' . $filename;
        file_put_contents($fullPath, $json);
        file_put_contents($auditDir . '/latest_' . $kind . '.json', $json);

        return $fullPath;
    }

    private static function slug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'import';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim($value, '_');

        return $value !== '' ? substr($value, 0, 80) : 'import';
    }
}
