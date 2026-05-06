<?php

namespace Vogel\Utils;

class ImportHistory
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    public static function read(string $historyFile): array
    {
        if (!file_exists($historyFile)) {
            return [];
        }

        $raw = file_get_contents($historyFile);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function prependEntry(string $historyFile, array $entry, int $limit = 100): array
    {
        $directory = dirname($historyFile);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nao foi possivel criar o diretorio do historico de importacao.');
        }

        $history = self::read($historyFile);
        array_unshift($history, $entry);

        if ($limit > 0 && count($history) > $limit) {
            $history = array_slice($history, 0, $limit);
        }

        $json = json_encode($history, self::JSON_FLAGS);
        if ($json === false) {
            throw new \RuntimeException('Nao foi possivel serializar o historico de importacao.');
        }

        $bytes = file_put_contents($historyFile, $json, LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException('Nao foi possivel gravar o historico de importacao.');
        }

        return $history;
    }
}
