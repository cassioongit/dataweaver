<?php

/**
 * Dataweaver - Update DBF Record
 * Updates a single record in RELAT_orto.DBF using copy-first semantics:
 * - Detects if there are actual changes
 * - Creates a backup only when changes exist
 * - Writes to a working copy and publishes it atomically
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(30);
date_default_timezone_set('America/Sao_Paulo');

ob_start();

$srcDir = __DIR__;
$projectRoot = dirname($srcDir);

require_once $srcDir . '/utils/Logger.php';
require_once $srcDir . '/utils/Auth.php';
require_once $srcDir . '/utils/Cors.php';
require_once $srcDir . '/utils/NativeDbf.php';

header('Content-Type: application/json');
\Vogel\Utils\Cors::apply();

$JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

$logger = \Vogel\Utils\Logger::getInstance();
$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

$backupFilePath = null;
$workingDbfPath = null;

function json_error_response(string $message, array $context = [], int $code = 500): void
{
    global $logger, $backupFilePath, $workingDbfPath, $JSON_FLAGS;

    if ($backupFilePath && file_exists($backupFilePath)) {
        @unlink($backupFilePath);
        $backupFilePath = null;
    }

    if ($workingDbfPath && file_exists($workingDbfPath)) {
        @unlink($workingDbfPath);
        $workingDbfPath = null;
    }

    $logger->error('[DBF_UPDATE] ' . $message, $context);

    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'erro' => $message,
        'details' => $context,
    ], $JSON_FLAGS);
    exit;
}

function ensure_directory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        json_error_response('Não foi possível criar o diretório', ['path' => $path]);
    }

    if (!is_writable($path)) {
        @chmod($path, 0777);
    }
}

function cleanup_directory(string $path, int $limit, $logger): void
{
    if (!is_dir($path)) {
        return;
    }

    $files = glob($path . '/*');
    if (empty($files)) {
        return;
    }

    usort($files, function ($a, $b) {
        return filemtime($a) <=> filemtime($b);
    });

    while (count($files) > $limit) {
        $oldest = array_shift($files);
        if (is_file($oldest)) {
            unlink($oldest);
            $logger->info('[DBF_UPDATE] Arquivo antigo excluído por retenção', [
                'path' => $oldest,
                'dir' => basename($path),
            ]);
        }
    }
}

function assert_supported_dbf(string $path, string $label = 'DBF'): void
{
    if (!file_exists($path)) {
        json_error_response($label . ' não encontrado.', ['path' => $path], 404);
    }

    $snapshot = \Vogel\Utils\NativeDbf::inspectHeader($path);
    if (!$snapshot['readable']) {
        json_error_response('Não foi possível abrir a base DBF.', ['path' => $path]);
    }
    if (($snapshot['issues'] ?? []) === ['short_header']) {
        json_error_response('Não foi possível validar o cabeçalho da base DBF.', ['path' => $path]);
    }

    if (!($snapshot['isValidLegacyDbf'] ?? false)) {
        json_error_response(
            'A base DBF precisa manter o formato legado original e o cabeçalho íntegro.',
            array_merge(['label' => $label], $snapshot)
        );
    }
}

function create_working_dbf_copy(string $sourcePath, string $targetDir): string
{
    $workingDbfPath = rtrim($targetDir, '/') . '/.RELAT_orto.wip.' . str_replace('.', '', uniqid('', true)) . '.DBF';

    if (file_exists($workingDbfPath)) {
        @unlink($workingDbfPath);
    }

    if (!copy($sourcePath, $workingDbfPath)) {
        json_error_response('Falha ao criar cópia de trabalho da base DBF.', [
            'from' => $sourcePath,
            'to' => $workingDbfPath,
        ]);
    }

    assert_supported_dbf($workingDbfPath, 'Cópia de trabalho DBF');

    return $workingDbfPath;
}

function commit_working_dbf_copy(string $workingPath, string $targetPath): void
{
    if (!rename($workingPath, $targetPath)) {
        json_error_response('Falha ao publicar a cópia de trabalho da base DBF.', [
            'from' => $workingPath,
            'to' => $targetPath,
        ]);
    }

    assert_supported_dbf($targetPath, 'Base DBF publicada');
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        json_error_response('Payload vazio.', [], 400);
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        json_error_response('JSON inválido.', [], 400);
    }

    return $payload;
}

try {
    $payload = read_json_body();

    $dbIndex = isset($payload['db_index']) ? (int) $payload['db_index'] : 0;
    if ($dbIndex <= 0) {
        json_error_response('Índice do registro inválido.', ['db_index' => $payload['db_index'] ?? null], 400);
    }

    $fields = $payload['fields'] ?? null;
    if (!is_array($fields)) {
        json_error_response('Campos inválidos.', ['fields_type' => gettype($fields)], 400);
    }

    $dbfPath = $projectRoot . '/database/RELAT_orto.DBF';
    assert_supported_dbf($dbfPath, 'Base DBF');

    $reader = new \Vogel\Utils\NativeDbf($dbfPath, 0);
    $current = $reader->getRecord($dbIndex);
    $reader->close();

    if (!$current || !empty($current['deleted'])) {
        json_error_response('Registro não encontrado.', ['db_index' => $dbIndex], 404);
    }

    $allowed = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
    $newData = [];
    for ($i = 0; $i <= 13; $i++) {
        $newData[$i] = isset($current[$i]) ? (string) $current[$i] : '';
    }

    $changes = [];
    foreach ($allowed as $idx) {
        if (!array_key_exists((string) $idx, $fields) && !array_key_exists($idx, $fields)) {
            continue;
        }

        $incoming = $fields[$idx] ?? $fields[(string) $idx] ?? '';
        $incoming = trim((string) $incoming);
        $existing = trim((string) ($current[$idx] ?? ''));

        if ($incoming !== $existing) {
            $newData[$idx] = $incoming;
            $changes[(string) $idx] = [
                'from' => $existing,
                'to' => $incoming,
            ];
        }
    }

    if (empty($changes)) {
        if (ob_get_length()) {
            ob_clean();
        }

        echo json_encode([
            'status' => 'noop',
            'db_index' => $dbIndex,
            'message' => 'Nenhuma alteração detectada.',
        ], $JSON_FLAGS);
        exit;
    }

    $backupDir = $projectRoot . '/backup';
    ensure_directory($backupDir);

    $existingBackups = glob($backupDir . '/RELAT_orto_*.DBF');
    $backupCount = 0;
    if (!empty($existingBackups)) {
        $indices = array_map(function ($path) {
            if (preg_match('/RELAT_orto_(\d+)\.DBF$/i', $path, $matches)) {
                return (int) $matches[1];
            }
            return 0;
        }, $existingBackups);
        $backupCount = max($indices);
    }

    $backupFilePath = $backupDir . '/RELAT_orto_' . ($backupCount + 1) . '.DBF';
    if (!copy($dbfPath, $backupFilePath)) {
        json_error_response('Falha ao realizar backup da base', ['from' => $dbfPath, 'to' => $backupFilePath]);
    }

    $backupFileRelative = str_replace($projectRoot . '/', '', $backupFilePath);
    $logger->info('[DBF_UPDATE] Backup realizado com sucesso', ['path' => $backupFilePath]);
    cleanup_directory($backupDir, 10, $logger);

    $workingDbfPath = create_working_dbf_copy($dbfPath, $projectRoot . '/database');
    $writer = new \Vogel\Utils\NativeDbf($workingDbfPath, 2);
    $ok = $writer->updateRecord($dbIndex, $newData);
    $writer->close();
    if (!$ok) {
        json_error_response('Falha ao atualizar o registro no DBF.', ['db_index' => $dbIndex]);
    }

    commit_working_dbf_copy($workingDbfPath, $dbfPath);
    $workingDbfPath = null;

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'status' => 'success',
        'db_index' => $dbIndex,
        'backup_file' => $backupFileRelative,
        'changes' => $changes,
        'message' => 'Registro atualizado com sucesso.',
    ], $JSON_FLAGS);
} catch (Exception $e) {
    json_error_response('Erro ao atualizar registro: ' . $e->getMessage());
}
