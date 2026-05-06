<?php

namespace Vogel\Utils;

/**
 * Minimal dotenv loader for local development.
 *
 * The built-in PHP server (`php -S`) does not automatically load `.env.local`.
 * We load it on-demand so backend auth (Supabase) works in dev without needing
 * to export variables manually.
 */
class Dotenv
{
    private static bool $loaded = false;

    public static function loadOnce(string $projectRoot): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $candidates = [
            rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env.local',
            rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env',
        ];

        foreach ($candidates as $path) {
            self::loadFile($path);
        }
    }

    private static function loadFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $key = $matches[1];
            $rawValue = trim($matches[2]);

            // Strip surrounding quotes (common in `.env.local`).
            if ($rawValue !== '' && (($rawValue[0] === '"' && str_ends_with($rawValue, '"')) || ($rawValue[0] === "'" && str_ends_with($rawValue, "'")))) {
                $rawValue = substr($rawValue, 1, -1);
            }

            // Do not override already-defined vars (production or shell-exported).
            if (getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $rawValue);
            $_ENV[$key] = $rawValue;
            $_SERVER[$key] = $rawValue;
        }
    }
}

