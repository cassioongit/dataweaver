<?php

namespace Vogel\Utils;

class Logger {
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    private string $logPath;
    private static ?Logger $instance = null;

    private function __construct() {
        date_default_timezone_set('America/Sao_Paulo');
        $this->logPath = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log(string $level, string $message, array $context = []): void {
        $level = strtoupper($level);
        $logFile = $this->logPath . '/system.log';
        
        // Manage log rotation (max 5MB)
        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            rename($logFile, $this->logPath . '/system_' . date('Y-m-d_His') . '.log');
        }

        $entry = [
            'ts' => date('Y-m-d H:i:s'),
            'level' => $level,
            'msg' => $message,
            'ctx' => $context,
            'user' => 'Dev' // Current session user
        ];

        file_put_contents($logFile, json_encode($entry, self::JSON_FLAGS) . PHP_EOF, FILE_APPEND);
    }

    public function info(string $message, array $context = []): void { $this->log('INFO', $message, $context); }
    public function warn(string $message, array $context = []): void { $this->log('WARN', $message, $context); }
    public function error(string $message, array $context = []): void { $this->log('ERROR', $message, $context); }
    public function debug(string $message, array $context = []): void { $this->log('DEBUG', $message, $context); }

    public function getLogs(int $limit = 50, ?string $level = null): array {
        $logFile = $this->logPath . '/system.log';
        if (!file_exists($logFile)) return [];

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];

        foreach (array_reverse($lines) as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            
            if ($level && $entry['level'] !== strtoupper($level)) continue;
            
            $logs[] = $entry;
            if (count($logs) >= $limit) break;
        }

        return $logs;
    }
}

if (!defined('PHP_EOF')) define('PHP_EOF', "\n");
