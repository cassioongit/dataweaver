<?php

/**
 * Vogel / Conciliação Digital
 * Import handler for CSV/XLS/XLSX uploads.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(0);
date_default_timezone_set('America/Sao_Paulo');

ob_start();

$srcDir = __DIR__;
$projectRoot = dirname($srcDir);

require_once $srcDir . '/utils/Logger.php';
require_once $srcDir . '/utils/Auth.php';
require_once $srcDir . '/utils/ImportAudit.php';
require_once $srcDir . '/utils/Cors.php';
require_once $srcDir . '/utils/TextEncoding.php';
require_once $projectRoot . '/vendor/csvtodbf/CharSVtoDbf.php';
include_once $projectRoot . '/vendor/phpoffice/phpexcel/Classes/PHPExcel/IOFactory.php';

header('Content-Type: application/json');
\Vogel\Utils\Cors::apply();

$JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
$uploadedFilePath = null;
$backupFilePath = null;
$workingDbfPath = null;
const MIN_NEW_DBF_ID = 28000;

$logger = \Vogel\Utils\Logger::getInstance();
$logger->info('[UPLOAD] Requisição recebida', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'cli',
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'file_name' => $_FILES['upl']['name'] ?? 'none',
]);

$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
$logger->info('[UPLOAD] Usuário autenticado', [
    'user_id' => $currentUser['id'] ?? null,
    'email' => $currentUser['email'] ?? null,
    'file_name' => $_FILES['upl']['name'] ?? 'none',
]);
unset($currentUser);

function json_error_response($message, $context = [], $code = 500)
{
    global $logger;
    global $uploadedFilePath;
    global $backupFilePath;
    global $workingDbfPath;
    global $JSON_FLAGS;

    if ($uploadedFilePath && file_exists($uploadedFilePath)) {
        @unlink($uploadedFilePath);
        $uploadedFilePath = null;
    }

    if ($backupFilePath && file_exists($backupFilePath)) {
        @unlink($backupFilePath);
        $backupFilePath = null;
    }

    if ($workingDbfPath && file_exists($workingDbfPath)) {
        @unlink($workingDbfPath);
        $workingDbfPath = null;
    }

    $logger->error($message, $context);

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

function ensure_directory($path)
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        json_error_response('Não foi possível criar o diretório', ['path' => $path]);
    }

    if (!is_writable($path)) {
        @chmod($path, 0777);
    }
}

function cleanup_directory($path, $limit, $logger)
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
            $logger->info('Arquivo antigo excluído por retenção', [
                'path' => $oldest,
                'dir' => basename($path),
            ]);
        }
    }
}

function assert_supported_dbf(string $path, string $label = 'DBF'): void
{
    if (!file_exists($path)) {
        json_error_response($label . ' não encontrado.', ['path' => $path]);
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
    assert_supported_dbf($workingPath, 'Cópia de trabalho DBF');

    if (!rename($workingPath, $targetPath)) {
        json_error_response('Falha ao publicar a cópia de trabalho da base DBF.', [
            'from' => $workingPath,
            'to' => $targetPath,
        ]);
    }

    assert_supported_dbf($targetPath, 'Base DBF publicada');
}

function formataData($data)
{
    $data = trim($data);
    if (strpos($data, ' ') !== false) {
        $data = explode(' ', $data)[0];
    }

    $parts = explode('/', $data);
    if (count($parts) !== 3) {
        return $data;
    }

    [$dia, $mes, $ano] = $parts;
    if (strlen($ano) === 2) {
        $ano = '20' . $ano;
    }

    return $ano . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
}

function cleanName($name)
{
    $name = trim($name);
    $name = preg_replace('/^Ortodontia Vogel;?["\']?/i', '', $name);
    $name = preg_replace('/["\']$/', '', $name);

    if (strpos($name, '";"') !== false) {
        $name = explode('";"', $name)[0];
    }

    $name = explode(';', $name)[0];
    return trim($name);
}

function upload_register_encoding(array &$stats, array &$rowEncoding, array $report, int $row)
{
    if (empty($report['issues'])) {
        return;
    }

    if (!isset($stats['rows_affected'])) {
        $stats['rows_affected'] = 0;
    }
    if (!isset($stats['issues_total'])) {
        $stats['issues_total'] = 0;
    }
    if (!isset($stats['fields'])) {
        $stats['fields'] = [];
    }
    if (!isset($stats['examples'])) {
        $stats['examples'] = [];
    }

    $field = $report['field'] ?? 'unknown';
    $stats['rows_affected']++;
    $stats['issues_total'] += count($report['issues']);
    $stats['fields'][$field] = ($stats['fields'][$field] ?? 0) + 1;

    if (count($stats['examples']) < 5) {
        $stats['examples'][] = [
            'row' => $row,
            'field' => $field,
            'original' => $report['original'],
            'corrected' => $report['value'],
            'issues' => $report['issues'],
        ];
    }

    $rowEncoding[] = $report;
}

function normalize_uploaded_rows($filePath, $extension)
{
    if ($extension === 'csv') {
        $rows = [];
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            json_error_response('Não foi possível ler o arquivo enviado.');
        }

        $encoding = mb_detect_encoding($contents, 'UTF-8, ISO-8859-1, Windows-1252', true) ?: 'UTF-8';
        if ($encoding !== 'UTF-8') {
            $contents = mb_convert_encoding($contents, 'UTF-8', $encoding);
        }
        if (substr($contents, 0, 3) === "\xef\xbb\xbf") {
            $contents = substr($contents, 3);
        }

        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $lines = explode("\n", $contents);

        $headerMatched = false;
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            $header = str_getcsv($line, ';', '"');
            if (isset($header[1]) && $header[1] === 'Nome' && isset($header[19]) && $header[19] === 'Indicado por') {
                $headerMatched = true;
                break;
            }

            if ($i > 5) {
                break;
            }
        }

        if (!$headerMatched) {
            json_error_response(
                'Formato de arquivo inválido. Padrão exigido não encontrado. Utilize o relatório correto de pacientes.',
                [],
                400
            );
        }

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, ';', '"');
        }

        return $rows;
    }

    $inputFileType = PHPExcel_IOFactory::identify($filePath);
    $objReader = PHPExcel_IOFactory::createReader($inputFileType);
    $objPHPExcel = $objReader->load($filePath);

    return $objPHPExcel->getActiveSheet()->toArray();
}

$allowed = ['csv', 'xls', 'xlsx'];

if (!isset($_FILES['upl']) || $_FILES['upl']['error'] !== 0) {
    json_error_response('Nenhum arquivo válido enviado.', [], 400);
}

$extension = strtolower(pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowed, true)) {
    json_error_response('Tipo de arquivo não permitido', ['extension' => $extension], 400);
}

$uploadsDir = $projectRoot . '/uploads';
ensure_directory($uploadsDir);

$uploadedFile = $uploadsDir . '/' . basename($_FILES['upl']['name']);
if (!move_uploaded_file($_FILES['upl']['tmp_name'], $uploadedFile)) {
    json_error_response('Não foi possível salvar o arquivo enviado', ['dest' => $uploadedFile]);
}

$uploadedFilePath = $uploadedFile;

cleanup_directory($uploadsDir, 10, $logger);

$dbfPath = $projectRoot . '/database/RELAT_orto.DBF';
assert_supported_dbf($dbfPath, 'Banco de dados oficial');
$logger->info('[UPLOAD] Header da base antes da importação', \Vogel\Utils\NativeDbf::inspectHeader($dbfPath));

try {
    $rows = normalize_uploaded_rows($uploadedFile, $extension);
} catch (Exception $e) {
    json_error_response('Erro ao processar arquivos base: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
}

try {
    $source = new CharSVtoDBF($dbfPath, [], 0);
    $maxId = $source->getMaxId();
    $id = max(MIN_NEW_DBF_ID, $maxId + 1);

    $addedPatients = [];
    $updatedPatientsCount = 0;
    $duplicatesCount = 0;
    $errorCount = 0;
    $count = 0;
    $auditRows = [];
    $encodingStats = [];
    $plannedWrites = [];
    $plannedLookup = [];
    $plannedInsertCount = 0;
    $originalRecordCount = $source->getNumRows();

    foreach ($rows as $line) {
        $count++;

        $minRow = ($extension === 'csv') ? 2 : 6;
        if ($count < $minRow) {
            continue;
        }

        $rowEncoding = [];

        $nomeRaw = ($extension === 'csv') ? ($line[1] ?? '') : ($line[0] ?? '');
        $nomeRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($nomeRaw, 'nome', $nomeEncoding);
        upload_register_encoding($encodingStats, $rowEncoding, $nomeEncoding, $count);
        if (empty($nomeRaw) || trim($nomeRaw) === 'Relatório desenvolvido por MedKey') {
            continue;
        }

        if ($extension === 'csv') {
            $respRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[19] ?? '', 'responsavel', $respEncoding);
            upload_register_encoding($encodingStats, $rowEncoding, $respEncoding, $count);
            $cpfRaw  = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[3] ?? '', 'cpf', $cpfEncoding);
            upload_register_encoding($encodingStats, $rowEncoding, $cpfEncoding, $count);
            $cepRaw  = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[20] ?? '', 'cep', $cepEncoding);
            upload_register_encoding($encodingStats, $rowEncoding, $cepEncoding, $count);
            $cidRaw  = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[24] ?? '', 'localidade', $cidEncoding);
            upload_register_encoding($encodingStats, $rowEncoding, $cidEncoding, $count);
            $ufRaw   = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[25] ?? '', 'uf', $ufEncoding);
            upload_register_encoding($encodingStats, $rowEncoding, $ufEncoding, $count);
            $dataRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[7] ?? '', 'data', $dataEncoding);
            upload_register_encoding($encodingStats, $rowEncoding, $dataEncoding, $count);
        } else {
            $respRaw = '';
            $cpfRaw  = '';
            $cepRaw  = '';
            $cidRaw  = '';
            $ufRaw   = '';
            $dataRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[4] ?? '', 'data', $dataEncoding);
            upload_register_encoding($encodingStats, $rowEncoding, $dataEncoding, $count);
        }

        $dataClean = trim(str_replace(['am', '0:00', 'pm'], '', $dataRaw));
        $dataFinal = formataData($dataClean) . ' 10:10';
        unset($dataFinal);

        $nomeClean = cleanName($nomeRaw);
        $nomeParaInsercao = mb_substr($nomeClean, 0, 38, 'UTF-8');
        $existing = $plannedLookup[$nomeParaInsercao] ?? $source->findRecordByName($nomeParaInsercao);
        if ($existing) {
            $plannedLookup[$nomeParaInsercao] = $existing;
        }

        if (!$existing) {
            $dados = [
                (int) $id,
                $nomeParaInsercao,
                mb_substr(trim($respRaw), 0, 33, 'UTF-8'),
                '',
                mb_substr(trim($cidRaw), 0, 25, 'UTF-8'),
                mb_substr(trim($ufRaw), 0, 2, 'UTF-8'),
                mb_substr(trim($cepRaw), 0, 9, 'UTF-8'),
                mb_substr(trim($cpfRaw), 0, 20, 'UTF-8'),
                '',
                '',
                '',
                '',
                'Residencial',
                'VOGEL',
            ];

            $plannedWrites[] = [
                'type' => 'insert',
                'data' => $dados,
            ];
            $plannedLookup[$nomeParaInsercao] = [
                'index' => $originalRecordCount + $plannedInsertCount + 1,
                'data' => $dados,
            ];
            $plannedInsertCount++;

            $reason = 'Novo paciente importado com sucesso.';
            if (mb_strlen($nomeClean, 'UTF-8') > 38) {
                $reason .= ' Nome truncado para 38 caracteres.';
            }
            $addedPatients[] = [
                'nome' => $nomeClean,
                'id' => $id,
                'type' => 'new',
                'truncated' => mb_strlen($nomeClean, 'UTF-8') > 38,
                'data' => date('H:i'),
            ];
            if (!empty($rowEncoding)) {
                $addedPatients[array_key_last($addedPatients)]['encoding'] = $rowEncoding;
            }
            $auditRows[] = [
                'row' => $count,
                'nome_original' => $nomeRaw,
                'nome_normalizado' => $nomeParaInsercao,
                'dbf_id' => $id,
                'action' => 'inserted',
                'reason' => $reason,
                'truncated' => mb_strlen($nomeClean, 'UTF-8') > 38,
                'fields' => [
                    'responsavel' => trim($respRaw),
                    'cidade' => trim($cidRaw),
                    'uf' => trim($ufRaw),
                    'cep' => trim($cepRaw),
                    'cpf' => trim($cpfRaw),
                ],
            ];
            if (!empty($rowEncoding)) {
                $auditRows[array_key_last($auditRows)]['encoding'] = $rowEncoding;
            }
            $id++;
        } else {
            $dbData = $existing['data'];
            $hasUpdates = false;
            $updatedData = $dbData;
            $filledFields = [];

            $fileValues = [
                2 => trim($respRaw),
                4 => trim($cidRaw),
                5 => trim($ufRaw),
                6 => trim($cepRaw),
                7 => trim($cpfRaw),
            ];

            foreach ($fileValues as $idx => $newVal) {
                $currentVal = trim($dbData[$idx] ?? '');
                if ($currentVal === '' && $newVal !== '') {
                    $hasUpdates = true;
                    $updatedData[$idx] = $newVal;
                    $filledFields[] = $idx;
                }
            }

            if ($hasUpdates) {
                $dataForUpdate = [];
                foreach ($updatedData as $key => $val) {
                    if (is_int($key)) {
                        $dataForUpdate[$key] = $val;
                    }
                }

                $plannedWrites[] = [
                    'type' => 'update',
                    'index' => $existing['index'],
                    'data' => $dataForUpdate,
                ];
                $plannedLookup[$nomeParaInsercao] = [
                    'index' => $existing['index'],
                    'data' => $dataForUpdate,
                ];

                $updatedPatientsCount++;
                $fieldLabels = [];
                foreach ($filledFields as $idx) {
                    $fieldLabels[] = match ($idx) {
                        2 => 'responsavel',
                        4 => 'cidade',
                        5 => 'uf',
                        6 => 'cep',
                        7 => 'cpf',
                        default => (string) $idx,
                    };
                }
                $addedPatients[] = [
                    'nome' => $nomeClean,
                    'id' => $dbData[0],
                    'type' => 'update',
                    'truncated' => mb_strlen($nomeClean, 'UTF-8') > 38,
                    'data' => date('H:i'),
                ];
                if (!empty($rowEncoding)) {
                    $addedPatients[array_key_last($addedPatients)]['encoding'] = $rowEncoding;
                }
                $auditRows[] = [
                    'row' => $count,
                    'nome_original' => $nomeRaw,
                    'nome_normalizado' => $nomeParaInsercao,
                    'dbf_id' => $dbData[0],
                    'dbf_index' => $existing['index'],
                    'action' => 'updated',
                    'reason' => 'Campos preenchidos: ' . implode(', ', $fieldLabels),
                    'truncated' => mb_strlen($nomeClean, 'UTF-8') > 38,
                    'fields' => [
                        'responsavel' => trim($respRaw),
                        'cidade' => trim($cidRaw),
                        'uf' => trim($ufRaw),
                        'cep' => trim($cepRaw),
                        'cpf' => trim($cpfRaw),
                    ],
                ];
                if (!empty($rowEncoding)) {
                    $auditRows[array_key_last($auditRows)]['encoding'] = $rowEncoding;
                }
            } else {
                $duplicatesCount++;
                $auditRows[] = [
                    'row' => $count,
                    'nome_original' => $nomeRaw,
                    'nome_normalizado' => $nomeParaInsercao,
                    'dbf_id' => $dbData[0],
                    'dbf_index' => $existing['index'],
                    'action' => 'duplicate',
                    'reason' => 'Registro já existe na base e não havia campos vazios para enriquecer.',
                    'truncated' => mb_strlen($nomeClean, 'UTF-8') > 38,
                    'fields' => [
                        'responsavel' => trim($respRaw),
                        'cidade' => trim($cidRaw),
                        'uf' => trim($ufRaw),
                        'cep' => trim($cepRaw),
                        'cpf' => trim($cpfRaw),
                    ],
                ];
                if (!empty($rowEncoding)) {
                    $auditRows[array_key_last($auditRows)]['encoding'] = $rowEncoding;
                }
            }
        }
    }

    $processedItems = count($addedPatients) + $duplicatesCount;
    $hasChanges = count($plannedWrites) > 0;
    $auditFile = null;
    $backupFileRelative = null;

    $source->closeDbase();

    if ($hasChanges) {
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

        $newBackupPath = $backupDir . '/RELAT_orto_' . ($backupCount + 1) . '.DBF';
        if (!copy($dbfPath, $newBackupPath)) {
            json_error_response('Falha ao realizar backup da base', ['from' => $dbfPath, 'to' => $newBackupPath]);
        }

        $backupFilePath = $newBackupPath;
        $backupFileRelative = str_replace($projectRoot . '/', '', $newBackupPath);

        $logger->info('Backup realizado com sucesso', ['path' => $newBackupPath]);
        cleanup_directory($backupDir, 10, $logger);

        $workingDbfPath = create_working_dbf_copy($dbfPath, $projectRoot . '/database');
        $logger->info('[UPLOAD] Header da cópia de trabalho antes da gravação', \Vogel\Utils\NativeDbf::inspectHeader($workingDbfPath));
        $writer = new CharSVtoDBF($workingDbfPath, [], 2);

        foreach ($plannedWrites as $write) {
            if (($write['type'] ?? '') === 'insert') {
                if (!$writer->insertOne($write['data'])) {
                    throw new Exception('Falha ao inserir o registro no DBF.');
                }
            } elseif (($write['type'] ?? '') === 'update') {
                if (!$writer->editOne($write['index'], $write['data'])) {
                    throw new Exception('Falha ao atualizar o registro no DBF.');
                }
            }
        }

        $writer->closeDbase();
        $logger->info('[UPLOAD] Header da cópia de trabalho após gravação', array_merge(
            \Vogel\Utils\NativeDbf::inspectHeader($workingDbfPath),
            [
                'planned_insert_count' => $plannedInsertCount,
                'planned_update_count' => count($plannedWrites) - $plannedInsertCount,
            ]
        ));

        commit_working_dbf_copy($workingDbfPath, $dbfPath);
        $workingDbfPath = null;
        $logger->info('[UPLOAD] Header da base publicada após importação', \Vogel\Utils\NativeDbf::inspectHeader($dbfPath));

        $historyFile = $projectRoot . '/database/import_history.json';
        $history = [];
        if (file_exists($historyFile)) {
            $history = json_decode(file_get_contents($historyFile), true) ?: [];
        }

        array_unshift($history, [
            'id' => uniqid(),
            'timestamp' => date('Y-m-d H:i:s'),
            'file_name' => $_FILES['upl']['name'] ?? 'Manual Upload',
            'added' => count($addedPatients) - $updatedPatientsCount,
            'updated' => $updatedPatientsCount,
            'duplicates' => $duplicatesCount,
            'errors' => $errorCount,
            'total_read' => $processedItems,
            'patients' => $addedPatients,
            'status' => 'completed',
            'audit_file' => null,
        ]);

        if (count($history) > 100) {
            $history = array_slice($history, 0, 100);
        }

        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    } else {
        if ($workingDbfPath && file_exists($workingDbfPath)) {
            @unlink($workingDbfPath);
        }
        $workingDbfPath = null;

        if ($uploadedFilePath && file_exists($uploadedFilePath)) {
            @unlink($uploadedFilePath);
        }
        $uploadedFilePath = null;
    }

    $auditPayload = [
        'kind' => 'upload',
        'file_name' => $_FILES['upl']['name'] ?? 'Manual Upload',
        'source_file' => basename($uploadedFile),
        'dbf' => 'database/RELAT_orto.DBF',
        'backup_file' => $backupFileRelative,
        'generated_at' => date('c'),
        'summary' => [
            'inserted' => count(array_filter($auditRows, fn($row) => ($row['action'] ?? '') === 'inserted')),
            'updated' => $updatedPatientsCount,
            'duplicate' => $duplicatesCount,
            'errors' => $errorCount,
            'total_rows' => count($auditRows),
        ],
        'encoding' => [
            'rows_affected' => $encodingStats['rows_affected'] ?? 0,
            'issues_total' => $encodingStats['issues_total'] ?? 0,
            'fields' => $encodingStats['fields'] ?? [],
            'examples' => $encodingStats['examples'] ?? [],
            'message' => 'O sistema vai normalizar textos para UTF-8, corrigir espaços em iniciais quando for seguro e registrar cada alteração no log.',
        ],
        'rows' => $auditRows,
    ];

    try {
        $auditFile = \Vogel\Utils\ImportAudit::writeReport($projectRoot, 'upload', $auditPayload);
    } catch (Exception $e) {
        $logger->error('[UPLOAD] Falha ao gravar auditoria', ['error' => $e->getMessage()]);
    }

    $logger->info('Processamento concluído', [
        'added' => count($addedPatients) - $updatedPatientsCount,
        'updated' => $updatedPatientsCount,
        'duplicates' => $duplicatesCount,
        'errors' => $errorCount,
        'encoding_rows' => $encodingStats['rows_affected'] ?? 0,
        'encoding_issues' => $encodingStats['issues_total'] ?? 0,
        'audit_file' => $auditFile ? str_replace($projectRoot . '/', '', $auditFile) : null,
    ]);

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'status' => 'success',
        'file' => 'api/database/RELAT_orto.DBF',
        'total_read' => $processedItems,
        'divergences' => count($addedPatients),
        'added' => count($addedPatients) - $updatedPatientsCount,
        'updated' => $updatedPatientsCount,
        'duplicates' => $duplicatesCount,
        'errors' => $errorCount,
        'encoding' => [
            'rows_affected' => $encodingStats['rows_affected'] ?? 0,
            'issues_total' => $encodingStats['issues_total'] ?? 0,
            'fields' => $encodingStats['fields'] ?? [],
            'examples' => $encodingStats['examples'] ?? [],
            'message' => 'O sistema vai normalizar textos para UTF-8, corrigir espaços em iniciais quando for seguro e registrar cada alteração no log.',
        ],
        'patients_added' => $addedPatients,
        'audit_file' => $auditFile ? str_replace($projectRoot . '/', '', $auditFile) : null,
        'no_changes' => !$hasChanges,
    ], $JSON_FLAGS);
    exit;
} catch (Exception $e) {
    json_error_response('Erro durante a importação: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
}
