<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$workspaceRoot = dirname(__DIR__);
$fixtureCsv = $workspaceRoot . '/api/uploads/12_2025-09-01 00_00_00_2026-04-14 00_00_00 - Copia.csv';
$fixtureDbf = $workspaceRoot . '/api/database/RELAT_orto.DBF';

if (!file_exists($fixtureCsv)) {
    fwrite(STDERR, "CSV fixture not found: $fixtureCsv\n");
    exit(1);
}

if (!file_exists($fixtureDbf)) {
    fwrite(STDERR, "DBF fixture not found: $fixtureDbf\n");
    exit(1);
}

$tmpRoot = sys_get_temp_dir() . '/dataweaver-upload-cli-' . str_replace('.', '', uniqid('', true));
$tmpApiRoot = $tmpRoot . '/api';
$tmpSrcRoot = $tmpApiRoot . '/src';

foreach ([
    $tmpRoot,
    $tmpApiRoot,
    $tmpSrcRoot,
    $tmpApiRoot . '/vendor',
    $tmpApiRoot . '/database',
    $tmpApiRoot . '/uploads',
    $tmpApiRoot . '/backup',
    $tmpApiRoot . '/logs/import_audits',
] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "Could not create directory: $dir\n");
        exit(1);
    }
}

copy_tree($workspaceRoot . '/api/src', $tmpSrcRoot);
copy_tree($workspaceRoot . '/api/vendor/csvtodbf', $tmpApiRoot . '/vendor/csvtodbf');
copy($fixtureDbf, $tmpApiRoot . '/database/RELAT_orto.DBF');
create_trimmed_csv_fixture($fixtureCsv, $tmpRoot . '/fixture.csv', 6);

if (!symlink($workspaceRoot . '/api/vendor/phpoffice', $tmpApiRoot . '/vendor/phpoffice')) {
    fwrite(STDERR, "Could not create phpoffice symlink.\n");
    exit(1);
}

$runner = $tmpRoot . '/run-upload.php';
$runnerCode = <<<'PHP'
<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$tmpRoot = __DIR__;
$apiRoot = $tmpRoot . '/api';
$fixture = $tmpRoot . '/fixture.csv';

putenv('DATAWEAVER_CLI_TEST_USER_JSON={"id":"cli-test-user","email":"cli@test.local"}');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/src/upload.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_FILES = [
    'upl' => [
        'name' => basename($fixture),
        'type' => 'text/csv',
        'tmp_name' => $fixture,
        'error' => 0,
        'size' => filesize($fixture),
    ],
];

require $apiRoot . '/src/upload.php';
PHP;

file_put_contents($runner, $runnerCode);

$beforeDbfHash = hash_file('sha256', $tmpApiRoot . '/database/RELAT_orto.DBF');
$beforeCount = count_history_entries($tmpApiRoot . '/database/import_history.json');

$command = 'php ' . escapeshellarg($runner);
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Upload runner failed with exit code $exitCode.\n");
    fwrite(STDERR, implode("\n", $output) . "\n");
    exit($exitCode);
}

$json = trim(implode("\n", $output));
$response = json_decode($json, true);
if (!is_array($response)) {
    fwrite(STDERR, "Upload runner did not return valid JSON.\n");
    fwrite(STDERR, $json . "\n");
    exit(1);
}

$historyFile = $tmpApiRoot . '/database/import_history.json';
$history = json_decode((string) file_get_contents($historyFile), true);
if (!is_array($history) || empty($history)) {
    fwrite(STDERR, "Import history was not created.\n");
    exit(1);
}

$latestUpload = $tmpApiRoot . '/logs/import_audits/latest_upload.json';
if (!file_exists($latestUpload)) {
    fwrite(STDERR, "Latest upload audit was not created.\n");
    exit(1);
}

$afterDbfHash = hash_file('sha256', $tmpApiRoot . '/database/RELAT_orto.DBF');

echo json_encode([
    'status' => 'ok',
    'response' => $response,
    'history_entries_before' => $beforeCount,
    'history_entries_after' => count($history),
    'latest_history' => $history[0],
    'history_file' => $historyFile,
    'latest_upload' => $latestUpload,
    'dbf_hash_before' => $beforeDbfHash,
    'dbf_hash_after' => $afterDbfHash,
    'dbf_changed' => $beforeDbfHash !== $afterDbfHash,
    'tmp_root' => $tmpRoot,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function copy_tree(string $source, string $destination): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $target = $destination . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                throw new RuntimeException('Could not create directory: ' . $target);
            }
            continue;
        }

        $parentDir = dirname($target);
        if (!is_dir($parentDir) && !mkdir($parentDir, 0777, true) && !is_dir($parentDir)) {
            throw new RuntimeException('Could not create directory: ' . $parentDir);
        }

        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('Could not copy file: ' . $item->getPathname());
        }
    }
}

function count_history_entries(string $historyFile): int
{
    if (!file_exists($historyFile)) {
        return 0;
    }

    $decoded = json_decode((string) file_get_contents($historyFile), true);
    return is_array($decoded) ? count($decoded) : 0;
}

function create_trimmed_csv_fixture(string $sourceFile, string $targetFile, int $maxLines): void
{
    $contents = file_get_contents($sourceFile);
    if ($contents === false) {
        throw new RuntimeException('Could not read CSV fixture: ' . $sourceFile);
    }

    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    $lines = array_values(array_filter(explode("\n", $contents), static fn (string $line): bool => trim($line) !== ''));
    $trimmed = array_slice($lines, 0, $maxLines);

    if (count($trimmed) < 2) {
        throw new RuntimeException('CSV fixture does not contain enough lines.');
    }

    $payload = implode("\n", $trimmed) . "\n";
    if (file_put_contents($targetFile, $payload) === false) {
        throw new RuntimeException('Could not write trimmed CSV fixture: ' . $targetFile);
    }
}
