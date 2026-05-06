<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/api/src/utils/ImportHistory.php';
require_once $projectRoot . '/api/src/utils/ImportAudit.php';

$tmpRoot = sys_get_temp_dir() . '/dataweaver-import-history-' . str_replace('.', '', uniqid('', true));
$historyFile = $tmpRoot . '/database/import_history.json';

mkdir($tmpRoot . '/database', 0777, true);

$entry = [
    'id' => 'smoke-test',
    'timestamp' => date('Y-m-d H:i:s'),
    'file_name' => 'smoke-test.csv',
    'added' => 1,
    'updated' => 2,
    'duplicates' => 0,
    'errors' => 0,
    'total_read' => 3,
    'patients' => [],
    'status' => 'completed',
    'audit_file' => null,
];

\Vogel\Utils\ImportHistory::prependEntry($historyFile, $entry);
$history = \Vogel\Utils\ImportHistory::read($historyFile);
$auditFile = \Vogel\Utils\ImportAudit::writeReport($tmpRoot, 'upload', [
    'kind' => 'upload',
    'file_name' => $entry['file_name'],
    'generated_at' => date('c'),
    'summary' => [
        'inserted' => $entry['added'],
        'updated' => $entry['updated'],
        'duplicate' => $entry['duplicates'],
        'errors' => $entry['errors'],
        'total_rows' => $entry['total_read'],
    ],
    'rows' => [],
]);

if (($history[0]['id'] ?? null) !== 'smoke-test') {
    fwrite(STDERR, "History entry was not persisted correctly.\n");
    exit(1);
}

if (!file_exists($auditFile)) {
    fwrite(STDERR, "Audit file was not created.\n");
    exit(1);
}

$latestUpload = $tmpRoot . '/logs/import_audits/latest_upload.json';
if (!file_exists($latestUpload)) {
    fwrite(STDERR, "Latest upload audit file was not created.\n");
    exit(1);
}

echo json_encode([
    'status' => 'ok',
    'history_file' => $historyFile,
    'latest_entry' => $history[0],
    'audit_file' => $auditFile,
    'latest_upload' => $latestUpload,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
