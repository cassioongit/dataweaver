<?php

namespace Vogel\Utils;

class Auth
{
    public static function requireAuthenticatedUser(): array
    {
        $cliUser = self::getCliTestUser();
        if ($cliUser !== null) {
            return $cliUser;
        }

        // Ensure `.env.local` is available when running the PHP built-in server in dev.
        // This avoids "backend auth not configured" failures when env vars weren't exported.
        require_once __DIR__ . '/Dotenv.php';
        \Vogel\Utils\Dotenv::loadOnce(dirname(__DIR__, 3));

        $token = self::getBearerToken();
        if (!$token) {
            self::fail('Acesso nao autorizado', 401);
        }

        $supabaseUrl = self::getEnvValue(['SUPABASE_URL', 'VITE_SUPABASE_URL']);
        $supabaseAnonKey = self::getEnvValue([
            'SUPABASE_PUBLISHABLE_KEY',
            'SUPABASE_ANON_KEY',
            'VITE_SUPABASE_PUBLISHABLE_KEY',
            'VITE_SUPABASE_ANON_KEY',
        ]);

        if (!$supabaseUrl || !$supabaseAnonKey) {
            self::fail('Autenticacao do backend nao configurada', 500);
        }

        [$status, $body] = self::requestJson(
            rtrim($supabaseUrl, '/') . '/auth/v1/user',
            [
                'Authorization: Bearer ' . $token,
                'apikey: ' . $supabaseAnonKey,
                'Accept: application/json',
            ]
        );

        if ($status !== 200) {
            self::fail('Acesso nao autorizado', 401, ['status' => $status]);
        }

        $user = json_decode($body, true);
        if (!is_array($user) || empty($user['id'])) {
            self::fail('Acesso nao autorizado', 401);
        }

        return $user;
    }

    private static function getCliTestUser(): ?array
    {
        if (PHP_SAPI !== 'cli') {
            return null;
        }

        $raw = getenv('DATAWEAVER_CLI_TEST_USER_JSON');
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['id'])) {
            self::fail('Usuario de teste CLI invalido', 500);
        }

        return $decoded;
    }

    private static function getEnvValue(array $names): ?string
    {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false && trim($value) !== '') {
                return trim($value);
            }

            if (isset($_ENV[$name]) && trim((string) $_ENV[$name]) !== '') {
                return trim((string) $_ENV[$name]);
            }

            if (isset($_SERVER[$name]) && trim((string) $_SERVER[$name]) !== '') {
                return trim((string) $_SERVER[$name]);
            }
        }

        return null;
    }

    private static function getBearerToken(): ?string
    {
        $header = self::readHeader('Authorization');
        if (!$header) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private static function readHeader(string $name): ?string
    {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $redirectKey = 'REDIRECT_HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (!empty($_SERVER[$headerKey])) {
            return trim((string) $_SERVER[$headerKey]);
        }

        if (!empty($_SERVER[$redirectKey])) {
            return trim((string) $_SERVER[$redirectKey]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $headerName => $value) {
                if (strcasecmp($headerName, $name) === 0) {
                    return trim((string) $value);
                }
            }
        }

        return null;
    }

    private static function requestJson(string $url, array $headers): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return [$status, $body === false ? '' : $body];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        $status = 0;
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            $status = (int) $matches[1];
        }

        return [$status, $body === false ? '' : $body];
    }

    private static function fail(string $message, int $code = 401, array $context = []): void
    {
        self::logFailure($message, $code, $context);

        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json');
        }

        echo json_encode([
            'status' => 'error',
            'erro' => $message,
            'details' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function logFailure(string $message, int $code, array $context = []): void
    {
        require_once __DIR__ . '/Logger.php';

        $logger = \Vogel\Utils\Logger::getInstance();
        $logger->warn('[AUTH] ' . $message, array_merge([
            'code' => $code,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'cli',
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], $context));
    }
}
