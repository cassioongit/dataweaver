<?php

/**
 * Vogel / Dataweaver
 * Preview / Dry-Run Endpoint
 *
 * Parses the uploaded CSV/XLSX and returns a reconciliation report
 * WITHOUT touching the DBF. Used to show the pre-import preview screen.
 *
 * Response shape:
 * {
 *   "status": "preview",
 *   "total": N,
 *   "new": [{ "nome", "nome_original", "data_cadastro", "truncated": bool, "row": N }],
 *   "existing": [{ "nome", "nome_original", "data_cadastro", "row": N }],
 *   "warnings": [{ "nome", "nome_original", "reason", "row": N }]
 * }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(60);
date_default_timezone_set('America/Sao_Paulo');

ob_start();

$srcDir     = __DIR__;
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

$logger = \Vogel\Utils\Logger::getInstance();
$logger->info('[PREVIEW] Requisição recebida', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'cli',
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'file_name' => $_FILES['upl']['name'] ?? 'none',
]);

$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
$logger->info('[PREVIEW] Usuário autenticado', [
    'user_id' => $currentUser['id'] ?? null,
    'email' => $currentUser['email'] ?? null,
    'file_name' => $_FILES['upl']['name'] ?? 'none',
]);
unset($currentUser);

$uploadedFile = null;
$uploadedFileName = $_FILES['upl']['name'] ?? 'Manual Upload';

function preview_error($message, $context = [], $code = 500)
{
    global $logger;
    global $uploadedFile;

    if ($uploadedFile && file_exists($uploadedFile)) {
        @unlink($uploadedFile);
        $uploadedFile = null;
    }

    $logger->error('[PREVIEW] ' . $message, $context);
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    echo json_encode(['status' => 'error', 'erro' => $message], $GLOBALS['JSON_FLAGS']);
    exit;
}

function formataDataPreview($data)
{
    $data = trim($data);
    if (strpos($data, ' ') !== false) {
        $data = explode(' ', $data)[0];
    }
    $parts = explode('/', $data);
    if (count($parts) !== 3) return $data;
    [$dia, $mes, $ano] = $parts;
    if (strlen($ano) === 2) $ano = '20' . $ano;
    return $ano . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
}

/**
 * Data Sanitizer (cleanName)
 */
function cleanName($name) {
    $name = trim($name);
    $name = preg_replace('/^Ortodontia Vogel;?["\']?/i', '', $name);
    $name = preg_replace('/["\']$/', '', $name);
    if (strpos($name, '";"') !== false) {
        $name = explode('";"', $name)[0];
    }
    $name = explode(';', $name)[0];
    return trim($name);
}

function preview_register_encoding(array &$stats, array &$rowEncoding, array $report, int $row)
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

// ─── Validate Upload ──────────────────────────────────────────────────────────

if (!isset($_FILES['upl']) || $_FILES['upl']['error'] !== 0) {
    preview_error('Nenhum arquivo válido enviado.', [], 400);
}

$allowed   = ['csv', 'xls', 'xlsx'];
$extension = strtolower(pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $allowed)) {
    preview_error('Tipo de arquivo não permitido.', ['extension' => $extension], 400);
}

$uploadsDir = $projectRoot . '/uploads';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

$uploadedFile = $uploadsDir . '/preview_' . basename($_FILES['upl']['name']);
if (!move_uploaded_file($_FILES['upl']['tmp_name'], $uploadedFile)) {
    preview_error('Não foi possível salvar o arquivo enviado.');
}

// ─── Parse File ───────────────────────────────────────────────────────────────

try {
    $dbfPath = $projectRoot . '/database/RELAT_orto.DBF';
    if (!file_exists($dbfPath)) {
        preview_error('Banco de dados DBF não encontrado.', ['path' => $dbfPath]);
    }

    $source = new CharSVtoDBF($dbfPath, [], 0);

    if ($extension === 'csv') {
        $rows = [];
        $headerMatched = false;
        if (($handle = fopen($uploadedFile, 'r')) !== false) {
            // Check first few rows for header
            for ($i = 0; $i < 5; $i++) {
                $header = fgetcsv($handle, 10000, ';');
                if ($header === false) break;
                if (isset($header[1]) && $header[1] === 'Nome' && isset($header[19]) && $header[19] === 'Indicado por') {
                    $headerMatched = true;
                    break;
                }
            }
            if (!$headerMatched) {
                fclose($handle);
                preview_error('Formato de arquivo inválido. Padrão exigido não encontrado. Utilize o relatório correto de pacientes.', [], 400);
            }
            
            // Read actual file now
            rewind($handle);
            while (($rowData = fgetcsv($handle, 10000, ';')) !== false) {
                $rows[] = $rowData;
            }
            fclose($handle);
        }
    } else {
        $inputFileType = PHPExcel_IOFactory::identify($uploadedFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($uploadedFile);
        $rows          = $objPHPExcel->getActiveSheet()->toArray();
    }
} catch (Exception $e) {
    preview_error('Erro ao processar arquivo: ' . $e->getMessage());
}

// ─── Reconcile ────────────────────────────────────────────────────────────────

$newPatients      = [];
$updatePatients   = [];
$existingPatients = [];
$warnings         = [];
$encodingStats    = [];
$count            = 0;
$minRow           = ($extension === 'csv') ? 2 : 6;

foreach ($rows as $line) {
    $count++;
    if ($count < $minRow) continue;

    $rowEncoding = [];

    $nomeRaw = ($extension === 'csv') ? ($line[1] ?? '') : ($line[0] ?? '');
    $nomeRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($nomeRaw, 'nome', $nomeEncoding);
    preview_register_encoding($encodingStats, $rowEncoding, $nomeEncoding, $count);
    $nomeRaw = trim($nomeRaw);

    if (empty($nomeRaw) || $nomeRaw === 'Relatório desenvolvido por MedKey') continue;

    // Clean name BEFORE truncation
    $nomeClean = cleanName($nomeRaw);

    // Map CSV Columns (same as upload.php)
    if ($extension === 'csv') {
        $respRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[19] ?? '', 'responsavel', $respEncoding);
        preview_register_encoding($encodingStats, $rowEncoding, $respEncoding, $count);
        $cpfRaw  = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[3] ?? '', 'cpf', $cpfEncoding);
        preview_register_encoding($encodingStats, $rowEncoding, $cpfEncoding, $count);
        $cepRaw  = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[20] ?? '', 'cep', $cepEncoding);
        preview_register_encoding($encodingStats, $rowEncoding, $cepEncoding, $count);
        $cidRaw  = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[24] ?? '', 'localidade', $cidEncoding);
        preview_register_encoding($encodingStats, $rowEncoding, $cidEncoding, $count);
        $ufRaw   = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[25] ?? '', 'uf', $ufEncoding);
        preview_register_encoding($encodingStats, $rowEncoding, $ufEncoding, $count);
        $dataRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[7] ?? '', 'data', $dataEncoding);
        preview_register_encoding($encodingStats, $rowEncoding, $dataEncoding, $count);
    } else {
        $respRaw = '';
        $cpfRaw  = '';
        $cepRaw  = '';
        $cidRaw  = '';
        $ufRaw   = '';
        $dataRaw = \Vogel\Utils\TextEncoding::sanitizeImportValue($line[4] ?? '', 'data', $dataEncoding);
        preview_register_encoding($encodingStats, $rowEncoding, $dataEncoding, $count);
    }

    // Check truncation (DBF field width = 38 chars)
    $isTruncated   = (mb_strlen($nomeClean, 'UTF-8') > 38);
    $nomeTruncated = mb_substr($nomeClean, 0, 38, 'UTF-8');

    // Encode for DBF comparison (same logic as upload.php)
    $nomeParaInsercao = $nomeTruncated;
    $nomeParaBusca    = $nomeParaInsercao;

    $entry = [
        'nome'          => $nomeParaBusca,
        'nome_original' => $nomeRaw,
        'responsavel'   => trim($respRaw),
        'documento'     => trim($cpfRaw),
        'localidade'    => trim($cidRaw) . ($ufRaw ? ", " . trim($ufRaw) : ""),
        'data_cadastro' => $dataRaw,
        'row'           => $count,
        'truncated'     => $isTruncated,
    ];

    $existing = $source->findRecordByName($nomeParaBusca);

    if (!$existing) {
        // É um registro realmente novo
        if ($isTruncated) {
            $entry['reason'] = 'Nome truncado de ' . mb_strlen($nomeRaw, 'UTF-8') . ' para 38 caracteres.';
            if (!empty($rowEncoding)) {
                $entry['encoding'] = $rowEncoding;
            }
            $warnings[] = $entry;
        } else {
            if (!empty($rowEncoding)) {
                $entry['encoding'] = $rowEncoding;
            }
            $newPatients[] = $entry;
        }
    } else {
        // Já existe. Verificamos se há dados novos para preencher campos vazios (Enriquecimento)
        $dbData = $existing['data'];
        $hasMissingData = false;
        $diff = [];

        // Mapeamento: Índice no DBF => Valor novo do arquivo
        $fileValues = [
            2 => trim($respRaw), // Responsável
            4 => trim($cidRaw),  // Cidade
            5 => trim($ufRaw),   // UF
            6 => trim($cepRaw),  // CEP
            7 => trim($cpfRaw)   // CPF
        ];

        foreach ($fileValues as $idx => $newVal) {
            $currentVal = trim($dbData[$idx] ?? '');
            if (empty($currentVal) && !empty($newVal)) {
                $hasMissingData = true;
                $diff[$idx] = [
                    'old' => '',
                    'new' => $newVal
                ];
            }
        }

        if ($hasMissingData) {
            $entry['diff'] = $diff;
            $entry['db_index'] = $existing['index'];
            if (!empty($rowEncoding)) {
                $entry['encoding'] = $rowEncoding;
            }
            $updatePatients[] = $entry;
        } else {
            if (!empty($rowEncoding)) {
                $entry['encoding'] = $rowEncoding;
            }
            $existingPatients[] = $entry;
        }
    }
}

// Clean up preview temp file
if ($uploadedFile && file_exists($uploadedFile)) {
    @unlink($uploadedFile);
    $uploadedFile = null;
}

if (ob_get_length()) ob_clean();

$logger->info('[PREVIEW] Reconciliação concluída', [
    'file'     => $uploadedFileName,
    'new'      => count($newPatients),
    'existing' => count($existingPatients),
    'warnings' => count($warnings),
    'encoding_rows' => $encodingStats['rows_affected'] ?? 0,
    'encoding_issues' => $encodingStats['issues_total'] ?? 0,
]);

$previewAudit = [
    'kind' => 'preview',
    'file_name' => $uploadedFileName,
    'source_csv' => $uploadedFileName,
    'dbf' => 'database/RELAT_orto.DBF',
    'generated_at' => date('c'),
    'summary' => [
        'new' => count($newPatients),
        'updates' => count($updatePatients),
        'existing' => count($existingPatients),
        'warnings' => count($warnings),
        'total' => count($newPatients) + count($existingPatients) + count($warnings) + count($updatePatients),
    ],
    'encoding' => [
        'rows_affected' => $encodingStats['rows_affected'] ?? 0,
        'issues_total' => $encodingStats['issues_total'] ?? 0,
        'fields' => $encodingStats['fields'] ?? [],
        'examples' => $encodingStats['examples'] ?? [],
        'message' => 'O sistema vai normalizar textos para UTF-8, corrigir espaços em iniciais quando for seguro e registrar cada alteração no log.',
    ],
    'new_patients' => $newPatients,
    'updates' => $updatePatients,
    'existing_patients' => $existingPatients,
    'warnings' => $warnings,
];

try {
    $previewAuditFile = \Vogel\Utils\ImportAudit::writeReport($projectRoot, 'preview', $previewAudit);
    $previewAudit['audit_file'] = str_replace($projectRoot . '/', '', $previewAuditFile);
} catch (Exception $e) {
    $logger->error('[PREVIEW] Falha ao gravar auditoria', ['error' => $e->getMessage()]);
}

echo json_encode([
    'status'   => 'preview',
    'file'     => $uploadedFileName,
    'total'    => count($newPatients) + count($existingPatients) + count($warnings) + count($updatePatients),
    'new'      => $newPatients,
    'updates'  => $updatePatients,
    'existing' => $existingPatients,
    'warnings' => $warnings,
    'encoding' => [
        'rows_affected' => $encodingStats['rows_affected'] ?? 0,
        'issues_total' => $encodingStats['issues_total'] ?? 0,
        'fields' => $encodingStats['fields'] ?? [],
        'examples' => $encodingStats['examples'] ?? [],
        'message' => 'O sistema vai normalizar textos para UTF-8, corrigir espaços em iniciais quando for seguro e registrar cada alteração no log.',
    ],
    'summary'  => [
        'new'     => count($newPatients),
        'updates' => count($updatePatients),
        'existing'=> count($existingPatients),
        'warnings'=> count($warnings)
    ],
    'audit_file' => $previewAudit['audit_file'] ?? null,
], $JSON_FLAGS);
