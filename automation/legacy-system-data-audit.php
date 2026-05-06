<?php
/**
 * Legacy system data audit (READ-ONLY).
 *
 * Generates docs/legacy-system-data-audit.md by scanning:
 *   legacy-system/src/data/(recursive)/*.DBF|csv|xls|xlsx
 *
 * Safety guarantees:
 * - DBF files are opened strictly in 'rb' mode (never writable)
 * - No input files are modified
 * - Output contains only aggregate counts (no PII)
 */
declare(strict_types=1);

// Keep CLI output clean (PHPExcel triggers a lot of deprecations on modern PHP).
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
@set_time_limit(0);

$projectRoot = realpath(__DIR__ . '/..') ?: getcwd();
$legacyDataRoot = $projectRoot . '/legacy-system/src/data';
$outputPath = $projectRoot . '/docs/legacy-system-data-audit.md';

require_once $projectRoot . '/api/src/utils/TextEncoding.php';
// NOTE: We intentionally do NOT include any upload/import endpoints or DBF writer/driver.
// This script reads DBF bytes directly and must remain side-effect free (read-only).

// Re-declare a local cleanName (kept consistent with api/src/upload.php).
function cleanNameLocal(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/^Ortodontia Vogel;?["\']?/i', '', $name) ?? $name;
    $name = preg_replace('/["\']$/', '', $name) ?? $name;

    if (strpos($name, '";"') !== false) {
        $name = explode('";"', $name)[0];
    }

    $name = explode(';', $name)[0];
    return trim($name);
}

function mdEscape(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = str_replace('|', '\|', $value);
    return $value;
}

function hexByte(int $b): string
{
    return '0x' . strtoupper(str_pad(dechex($b & 0xFF), 2, '0', STR_PAD_LEFT));
}

function dbfHeaderTypeLabel(int $version): string
{
    return match ($version) {
        0x02 => 'dBASE II (0x02)',
        0x03 => 'dBASE III (0x03)',
        0x04 => 'dBASE IV (0x04)',
        0x05 => 'dBASE V (0x05)',
        0x30 => 'Visual FoxPro (0x30)',
        0x31 => 'Visual FoxPro (0x31)',
        0x32 => 'Visual FoxPro (0x32)',
        0x43 => 'dBASE IV SQL table (0x43)',
        0x63 => 'dBASE IV SQL system (0x63)',
        0x83 => 'dBASE III + memo (0x83)',
        0x8B => 'dBASE IV + memo (0x8B)',
        0xCB => 'dBASE IV SQL + memo (0xCB)',
        0xF5 => 'FoxPro (0xF5)',
        0xFB => 'FoxPro (0xFB)',
        default => 'Desconhecido (' . hexByte($version) . ')',
    };
}

function normalizeNameKey(string $name): string
{
    $name = trim($name);
    $name = mb_strtolower($name, 'UTF-8');
    $name = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    $name = trim($name);
    if (mb_strlen($name, 'UTF-8') > 38) {
        $name = mb_substr($name, 0, 38, 'UTF-8');
    }
    return $name;
}

function isAsciiControl(int $b): bool
{
    // allow tab, lf, cr
    if ($b === 0x09 || $b === 0x0A || $b === 0x0D) return false;
    return $b >= 0x00 && $b < 0x20;
}

function hasWeirdBytes(string $bytes): bool
{
    static $undefinedCp1252 = [0x81 => true, 0x8D => true, 0x8F => true, 0x90 => true, 0x9D => true];
    $len = strlen($bytes);
    for ($i = 0; $i < $len; $i++) {
        $b = ord($bytes[$i]);
        if ($b === 0x00) return true;
        if (isAsciiControl($b)) return true;
        if (isset($undefinedCp1252[$b])) return true;
    }
    return false;
}

function strongEncodingIssues(array $issues): bool
{
    static $strong = [
        'reencoded_to_utf8' => true,
        'invalid_utf8_unfixed' => true,
        'replacement_character_detected' => true,
        'mojibake_repaired' => true,
    ];
    foreach ($issues as $issue) {
        if (isset($strong[(string) $issue])) return true;
    }
    return false;
}

/**
 * Parse DBF header fields descriptors trying both layouts.
 *
 * Returns:
 *  [
 *    'ok' => bool,
 *    'version' => int,
 *    'num_records' => int,
 *    'header_size' => int,
 *    'record_size' => int,
 *    'fields' => [ [name,type,length,offset], ... ],
 *    'name_field_index' => int|null,
 *    'scan_fields' => [field,...] (text fields)
 *  ]
 */
function parseDbfSchema(string $path): array
{
    $fh = fopen($path, 'rb');
    if (!$fh) {
        return ['ok' => false, 'error' => 'Não foi possível abrir DBF em modo leitura.'];
    }

    $hdrRaw = fread($fh, 32);
    if ($hdrRaw === false || strlen($hdrRaw) < 32) {
        fclose($fh);
        return ['ok' => false, 'error' => 'Header inválido (menos de 32 bytes).'];
    }

    $hdr = unpack('Cversion/Cyy/Cmm/Cdd/VnumRecords/vheaderSize/vrecordSize', $hdrRaw);
    if (!is_array($hdr)) {
        fclose($fh);
        return ['ok' => false, 'error' => 'Falha ao ler header.'];
    }

    $version = (int) ($hdr['version'] ?? 0);
    $numRecords = (int) ($hdr['numRecords'] ?? 0);
    $headerSize = (int) ($hdr['headerSize'] ?? 0);
    $recordSize = (int) ($hdr['recordSize'] ?? 0);

    $attempts = [
        ['descStart' => 32, 'descSize' => 32, 'nameLen' => 11, 'typeOffset' => 11, 'lenOffset' => 16],
        ['descStart' => 68, 'descSize' => 48, 'nameLen' => 32, 'typeOffset' => 32, 'lenOffset' => 33],
    ];

    $best = null;
    foreach ($attempts as $a) {
        $descStart = $a['descStart'];
        $descSize = $a['descSize'];
        if ($headerSize < ($descStart + 1)) {
            continue;
        }

        $rawFieldBytes = $headerSize - $descStart - 1;
        $numFieldsFloat = $rawFieldBytes / $descSize;
        $numFields = (int) floor($numFieldsFloat + 1e-9);
        if ($numFields <= 0) {
            continue;
        }
        // Must be an integer number of field descriptors
        if (abs($numFieldsFloat - $numFields) > 1e-6) {
            continue;
        }

        $fields = [];
        $offset = 1; // deletion flag
        $sumLengths = 0;

        fseek($fh, $descStart);
        for ($i = 0; $i < $numFields; $i++) {
            $raw = fread($fh, $descSize);
            if ($raw === false || strlen($raw) < $descSize) {
                break;
            }

            $name = trim(substr($raw, 0, $a['nameLen']), "\0 ");
            $type = substr($raw, $a['typeOffset'], 1);
            $len = ord(substr($raw, $a['lenOffset'], 1));
            if ($name === '' && $type === "\0" && $len === 0) {
                // padding/terminator area; stop
                break;
            }
            $fields[] = [
                'index' => $i,
                'name' => $name,
                'type' => $type,
                'length' => $len,
                'offset' => $offset,
            ];
            $offset += $len;
            $sumLengths += $len;
        }

        $computedRecordSize = 1 + $sumLengths;
        if ($computedRecordSize !== $recordSize) {
            continue;
        }

        $best = [
            'descStart' => $descStart,
            'descSize' => $descSize,
            'fields' => $fields,
            'computedRecordSize' => $computedRecordSize,
        ];
        break;
    }

    fclose($fh);

    if ($best === null) {
        return [
            'ok' => false,
            'error' => 'Não foi possível inferir o schema (descritores) de forma consistente com o record_size.',
            'version' => $version,
            'num_records' => $numRecords,
            'header_size' => $headerSize,
            'record_size' => $recordSize,
        ];
    }

    $fields = $best['fields'];
    $nameFieldIndex = null;
    foreach ($fields as $f) {
        if (!in_array($f['type'], ['C', 'M', 'G', 'V', 'W'], true)) continue;
        $n = mb_strtoupper((string) $f['name'], 'UTF-8');
        $n = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $n) ?: $n;
        if (preg_match('/NOME|PAC|PACIENTE|NOM/', $n)) {
            $nameFieldIndex = (int) $f['index'];
            break;
        }
    }
    if ($nameFieldIndex === null && isset($fields[1]) && in_array($fields[1]['type'], ['C', 'M', 'G', 'V', 'W'], true)) {
        $nameFieldIndex = (int) $fields[1]['index'];
    }

    $scanFields = [];
    foreach ($fields as $f) {
        if (in_array($f['type'], ['C', 'M', 'G', 'V', 'W'], true)) {
            $scanFields[] = $f;
        }
    }

    return [
        'ok' => true,
        'version' => $version,
        'num_records' => $numRecords,
        'header_size' => $headerSize,
        'record_size' => $recordSize,
        'fields' => $fields,
        'name_field_index' => $nameFieldIndex,
        'scan_fields' => $scanFields,
    ];
}

function analyzeDbfFile(string $path, string $relativeName): array
{
    $schema = parseDbfSchema($path);
    if (!$schema['ok']) {
        return [
            'name' => $relativeName,
            'records' => null,
            'header_type' => null,
            'duplicates' => null,
            'invalid' => null,
            'error' => $schema['error'] ?? 'Erro desconhecido ao parsear DBF.',
            'meta' => [
                'version' => $schema['version'] ?? null,
                'header_size' => $schema['header_size'] ?? null,
                'record_size' => $schema['record_size'] ?? null,
            ],
        ];
    }

    $version = (int) $schema['version'];
    $headerType = dbfHeaderTypeLabel($version);

    $numRecords = (int) $schema['num_records'];
    $headerSize = (int) $schema['header_size'];
    $recordSize = (int) $schema['record_size'];
    $fields = $schema['fields'];
    $scanFields = $schema['scan_fields'];
    $nameIndex = $schema['name_field_index'];

    $fh = fopen($path, 'rb');
    if (!$fh) {
        return [
            'name' => $relativeName,
            'records' => $numRecords,
            'header_type' => $headerType,
            'duplicates' => null,
            'invalid' => null,
            'error' => 'Não foi possível abrir DBF em modo leitura.',
            'meta' => [
                'version' => $version,
                'header_size' => $headerSize,
                'record_size' => $recordSize,
                'fields' => count($fields),
            ],
        ];
    }

    $duplicateCounts = [];
    $duplicatesTotal = 0;
    $invalidRecords = 0;
    $liveRecords = 0;

    $nameField = null;
    if ($nameIndex !== null) {
        foreach ($fields as $f) {
            if ((int) $f['index'] === (int) $nameIndex) {
                $nameField = $f;
                break;
            }
        }
    }

    for ($recNo = 1; $recNo <= $numRecords; $recNo++) {
        $pos = $headerSize + (($recNo - 1) * $recordSize);
        fseek($fh, $pos);
        $rawRec = fread($fh, $recordSize);
        if ($rawRec === false || strlen($rawRec) < $recordSize) {
            break;
        }

        if ($rawRec[0] === '*') {
            continue; // soft-delete
        }
        $liveRecords++;

        // Duplicate key
        $nameUtf8 = '';
        if ($nameField !== null) {
            $bytes = substr($rawRec, (int) $nameField['offset'], (int) $nameField['length']);
            $bytes = rtrim($bytes, " \0");
            $nameUtf8 = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
            $nameUtf8 = trim($nameUtf8);
        }

        if ($nameUtf8 !== '') {
            $key = normalizeNameKey($nameUtf8);
            if ($key !== '') {
                $duplicateCounts[$key] = ($duplicateCounts[$key] ?? 0) + 1;
            }
        }

        // Invalid record heuristic
        $isInvalid = false;
        foreach ($scanFields as $f) {
            $bytes = substr($rawRec, (int) $f['offset'], (int) $f['length']);
            if ($bytes === '') continue;
            if (hasWeirdBytes($bytes)) {
                $isInvalid = true;
                break;
            }
        }

        if (!$isInvalid && $nameUtf8 !== '') {
            $report = [];
            $sanitized = \Vogel\Utils\TextEncoding::sanitizeImportValue($nameUtf8, 'nome', $report);
            unset($sanitized);
            if (strongEncodingIssues($report['issues'] ?? [])) {
                $isInvalid = true;
            }
        }

        if ($isInvalid) {
            $invalidRecords++;
        }
    }

    fclose($fh);

    foreach ($duplicateCounts as $count) {
        if ($count > 1) $duplicatesTotal += ($count - 1);
    }

    return [
        'name' => $relativeName,
        'records' => $numRecords,
        'header_type' => $headerType,
        'duplicates' => $duplicatesTotal,
        'invalid' => $invalidRecords,
        'error' => null,
        'meta' => [
            'version' => $version,
            'header_size' => $headerSize,
            'record_size' => $recordSize,
            'fields' => count($fields),
            'live_records' => $liveRecords,
        ],
    ];
}

function detectTextEncoding(string $bytes): string
{
    $encoding = mb_detect_encoding($bytes, 'UTF-8, ISO-8859-1, Windows-1252', true);
    return $encoding ?: 'UTF-8';
}

function normalizeCsvRows(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Não foi possível ler o CSV.');
    }

    $encoding = detectTextEncoding($contents);
    if ($encoding !== 'UTF-8') {
        $contents = mb_convert_encoding($contents, 'UTF-8', $encoding);
    }
    if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
        $contents = substr($contents, 3);
    }
    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    $lines = explode("\n", $contents);

    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line, ';', '"', '\\');
    }
    return $rows;
}

function findCsvHeaderInfo(array $rows): array
{
    $headerRow = null;
    $colCount = null;

    $max = min(6, count($rows));
    for ($i = 0; $i < $max; $i++) {
        $row = $rows[$i];
        if (isset($row[1], $row[19]) && $row[1] === 'Nome' && $row[19] === 'Indicado por') {
            $headerRow = $i + 1; // 1-based for humans
            $colCount = count($row);
            break;
        }
    }

    if ($headerRow === null) {
        $headerRow = 1;
        $colCount = isset($rows[0]) ? count($rows[0]) : 0;
        return [
            'header_type' => 'CSV (header não reconhecido)',
            'header_row' => $headerRow,
            'data_start_row' => $headerRow + 1,
            'col_count' => $colCount,
            'matched' => false,
        ];
    }

    return [
        'header_type' => 'CSV MedKey (header linha ' . $headerRow . ')',
        'header_row' => $headerRow,
        'data_start_row' => $headerRow + 1,
        'col_count' => $colCount,
        'matched' => true,
    ];
}

function analyzeTabularFile(string $path, string $relativeName): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $type = strtoupper($ext);

    $records = 0;
    $duplicates = 0;
    $invalid = 0;
    $importKey = '';
    $headerType = '';
    $colCount = 0;
    $dataStartRow = 0;

    $nameKeys = [];
    $nameCounts = [];

    try {
        if ($ext === 'csv') {
            $rows = normalizeCsvRows($path);
            $hdr = findCsvHeaderInfo($rows);
            $headerType = $hdr['header_type'];
            $colCount = (int) $hdr['col_count'];
            $dataStartRow = (int) $hdr['data_start_row'];

            $importKey = 'CSV|' . $headerType . '|' . $colCount;

            for ($i = $dataStartRow - 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nomeRaw = (string) ($row[1] ?? '');
                $report = [];
                $nomeSan = \Vogel\Utils\TextEncoding::sanitizeImportValue($nomeRaw, 'nome', $report);
                $nomeClean = cleanNameLocal((string) $nomeSan);
                if ($nomeClean === '' || trim($nomeClean) === 'Relatório desenvolvido por MedKey') {
                    continue;
                }
                $nomeTrunc = mb_substr($nomeClean, 0, 38, 'UTF-8');
                $records++;

                $key = normalizeNameKey($nomeTrunc);
                if ($key !== '') {
                    $nameCounts[$key] = ($nameCounts[$key] ?? 0) + 1;
                }

                $isInvalid = false;
                if (strongEncodingIssues($report['issues'] ?? [])) {
                    $isInvalid = true;
                }

                foreach ([
                    ['idx' => 19, 'field' => 'responsavel'],
                    ['idx' => 3, 'field' => 'cpf'],
                    ['idx' => 20, 'field' => 'cep'],
                    ['idx' => 24, 'field' => 'localidade'],
                    ['idx' => 25, 'field' => 'uf'],
                    ['idx' => 7, 'field' => 'data'],
                ] as $spec) {
                    $raw = (string) ($row[$spec['idx']] ?? '');
                    $rep = [];
                    $val = \Vogel\Utils\TextEncoding::sanitizeImportValue($raw, $spec['field'], $rep);
                    unset($val);
                    if (strongEncodingIssues($rep['issues'] ?? [])) {
                        $isInvalid = true;
                        break;
                    }
                    if (strpos($raw, "\0") !== false) {
                        $isInvalid = true;
                        break;
                    }
                }

                if ($isInvalid) {
                    $invalid++;
                }
            }
        } elseif ($ext === 'xls' || $ext === 'xlsx') {
            require_once $GLOBALS['projectRoot'] . '/api/vendor/phpoffice/phpexcel/Classes/PHPExcel/IOFactory.php';

            // PHPExcel is noisy on modern PHP; suppress warnings during parsing but keep hard failures.
            $prevLevel = error_reporting();
            error_reporting($prevLevel & ~E_WARNING & ~E_NOTICE);
            try {
                $inputFileType = \PHPExcel_IOFactory::identify($path);
                $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
                $objPHPExcel = $objReader->load($path);
                $rows = $objPHPExcel->getActiveSheet()->toArray();
            } finally {
                error_reporting($prevLevel);
            }

            if (!is_array($rows)) {
                throw new RuntimeException('Falha ao extrair linhas do XLS/XLSX.');
            }

            $dataStartRow = 6;
            $colCount = 0;
            foreach ($rows as $r) {
                if (is_array($r)) $colCount = max($colCount, count($r));
            }
            $headerType = 'XLS/XLSX (dados a partir da linha 6)';
            $importKey = $type . '|' . $headerType . '|' . $colCount;

            for ($i = $dataStartRow - 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (!is_array($row)) continue;

                $nomeRaw = (string) ($row[0] ?? '');
                $report = [];
                $nomeSan = \Vogel\Utils\TextEncoding::sanitizeImportValue($nomeRaw, 'nome', $report);
                $nomeClean = cleanNameLocal((string) $nomeSan);
                if ($nomeClean === '' || trim($nomeClean) === 'Relatório desenvolvido por MedKey') {
                    continue;
                }
                $nomeTrunc = mb_substr($nomeClean, 0, 38, 'UTF-8');
                $records++;

                $key = normalizeNameKey($nomeTrunc);
                if ($key !== '') {
                    $nameCounts[$key] = ($nameCounts[$key] ?? 0) + 1;
                }

                $isInvalid = false;
                if (strongEncodingIssues($report['issues'] ?? [])) {
                    $isInvalid = true;
                }

                // XLS/XLSX legacy import uses only date in col[4] plus name; still scan a few likely fields if present
                foreach ([0 => 'nome', 4 => 'data'] as $idx => $field) {
                    $raw = (string) ($row[$idx] ?? '');
                    $rep = [];
                    $val = \Vogel\Utils\TextEncoding::sanitizeImportValue($raw, $field, $rep);
                    unset($val);
                    if (strongEncodingIssues($rep['issues'] ?? [])) {
                        $isInvalid = true;
                        break;
                    }
                    if (strpos($raw, "\0") !== false) {
                        $isInvalid = true;
                        break;
                    }
                }

                if ($isInvalid) {
                    $invalid++;
                }
            }
        } else {
            throw new RuntimeException('Tipo não suportado: ' . $ext);
        }
    } catch (Throwable $e) {
        return [
            'name' => $relativeName,
            'type' => $type,
            'records' => null,
            'duplicates' => null,
            'invalid' => null,
            'import_key' => null,
            'header_type' => null,
            'col_count' => null,
            'error' => $e->getMessage(),
        ];
    }

    foreach ($nameCounts as $count) {
        if ($count > 1) $duplicates += ($count - 1);
    }

    return [
        'name' => $relativeName,
        'type' => $type,
        'records' => $records,
        'duplicates' => $duplicates,
        'invalid' => $invalid,
        'import_key' => $importKey,
        'header_type' => $headerType,
        'col_count' => $colCount,
        'error' => null,
    ];
}

function collectFiles(string $root, array $extensions): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) continue;
        $out[] = $file->getPathname();
    }
    sort($out, SORT_STRING);
    return $out;
}

if (!is_dir($legacyDataRoot)) {
    fwrite(STDERR, "Diretório não encontrado: {$legacyDataRoot}\n");
    exit(1);
}

$dbfPaths = collectFiles($legacyDataRoot, ['dbf']);
$tabularPaths = collectFiles($legacyDataRoot, ['csv', 'xls', 'xlsx']);

$dbfRows = [];
$dbfSchemaVariants = [];
$dbfErrors = [];
foreach ($dbfPaths as $path) {
    $rel = ltrim(str_replace($legacyDataRoot, '', $path), '/');
    $result = analyzeDbfFile($path, $rel);
    $dbfRows[] = $result;

    if ($result['error'] === null && !empty($result['meta']['record_size']) && !empty($result['meta']['fields'])) {
        $variantKey = 'v=' . hexByte((int) ($result['meta']['version'] ?? 0)) .
            ' fields=' . (int) $result['meta']['fields'] .
            ' record_size=' . (int) $result['meta']['record_size'];
        $dbfSchemaVariants[$variantKey] = ($dbfSchemaVariants[$variantKey] ?? 0) + 1;
    }
    if (!empty($result['error'])) {
        $dbfErrors[] = [
            'type' => 'DBF',
            'name' => $rel,
            'error' => (string) $result['error'],
        ];
    }
}

$tabularRows = [];
$importGroups = [];
$tabularErrors = [];
foreach ($tabularPaths as $path) {
    $rel = ltrim(str_replace($legacyDataRoot, '', $path), '/');
    $result = analyzeTabularFile($path, $rel);
    $tabularRows[] = $result;
    if (!empty($result['import_key'])) {
        $importGroups[$result['import_key']] = ($importGroups[$result['import_key']] ?? 0) + 1;
    }
    if (!empty($result['error'])) {
        $tabularErrors[] = [
            'type' => (string) ($result['type'] ?? 'TABULAR'),
            'name' => $rel,
            'error' => (string) $result['error'],
        ];
    }
}

// Assign deterministic Importação labels
$importDefinitions = [];
$importLabelByKey = [];
ksort($importGroups, SORT_STRING);
$i = 1;
foreach ($importGroups as $key => $count) {
    $label = 'Importação ' . $i;
    $importLabelByKey[$key] = $label;
    $importDefinitions[] = [
        'label' => $label,
        'key' => $key,
        'files' => $count,
    ];
    $i++;
}

// Read-only inspection of the CURRENT DBF (system atual) for comparison section
$currentDbfPath = $projectRoot . '/api/database/RELAT_orto.DBF';
$currentDbfMeta = null;
if (is_file($currentDbfPath)) {
    $schema = parseDbfSchema($currentDbfPath);
    $currentDbfMeta = [
        'path' => 'api/database/RELAT_orto.DBF',
        'ok' => (bool) ($schema['ok'] ?? false),
        'version' => (int) ($schema['version'] ?? 0),
        'num_records' => (int) ($schema['num_records'] ?? 0),
        'header_size' => (int) ($schema['header_size'] ?? 0),
        'record_size' => (int) ($schema['record_size'] ?? 0),
        'fields' => isset($schema['fields']) && is_array($schema['fields']) ? count($schema['fields']) : null,
        'error' => $schema['error'] ?? null,
    ];

    // Check terminators/integrity quickly (read-only)
    $fh = fopen($currentDbfPath, 'rb');
    if ($fh) {
        $fileSize = filesize($currentDbfPath);
        fseek($fh, max(0, $currentDbfMeta['header_size'] - 1));
        $term = fread($fh, 1);
        $termByte = ($term !== false && strlen($term) === 1) ? ord($term) : null;
        if (is_int($fileSize) && $fileSize > 0) {
            fseek($fh, $fileSize - 1);
            $eof = fread($fh, 1);
            $eofByte = ($eof !== false && strlen($eof) === 1) ? ord($eof) : null;
            $currentDbfMeta['eof_byte'] = $eofByte;
        }
        $currentDbfMeta['header_terminator_byte'] = $termByte;
        $currentDbfMeta['file_size'] = $fileSize;
        $expectedMinSize = $currentDbfMeta['header_size'] + ($currentDbfMeta['num_records'] * $currentDbfMeta['record_size']) + 1;
        $currentDbfMeta['expected_min_size'] = $expectedMinSize;
        $currentDbfMeta['size_ok'] = is_int($fileSize) ? ($fileSize >= $expectedMinSize) : null;
        fclose($fh);
    }
}

// Render markdown
$now = date('Y-m-d H:i:s');
$md = [];
$md[] = '# Auditoria (read-only) de dados do legado';
$md[] = '';
$md[] = '**Gerado em:** ' . $now;
$md[] = '';
$md[] = 'Este relatório é **somente leitura**: não altera cabeçalho, estrutura, nem conteúdo de nenhum arquivo de entrada.';
$md[] = '';
$md[] = '## Contexto e critérios';
$md[] = '';
$md[] = '- Fonte: `legacy-system/src/data/` (recursivo).';
$md[] = '- Tipos analisados: `DBF`, `CSV`, `XLS`, `XLSX`.';
$md[] = '- Duplicados: por **nome normalizado** (trim → lower → translit ASCII → colapsar espaços → truncar 38).';
$md[] = '- Inválidos: contagem por registro/linha afetada quando houver **bytes estranhos** (NULL/controles/CP1252 indefinido) e/ou **issues fortes** de encoding (UTF-8 inválido, `�`, mojibake reparado, reencode).';
$md[] = '';

if (!empty($dbfSchemaVariants)) {
    arsort($dbfSchemaVariants, SORT_NUMERIC);
    $md[] = '### Variantes de schema DBF detectadas (heurístico)';
    $md[] = '';
    foreach ($dbfSchemaVariants as $k => $count) {
        $md[] = '- ' . mdEscape($k) . ' → ' . $count . ' arquivo(s)';
    }
    $md[] = '';
}

$md[] = '## 1) Arquivos DBF';
$md[] = '';
$md[] = '| Nome do arquivo | Quantidade de registros | Tipo de cabeçalho | Registros duplicados | Registros com caracteres inválidos |';
$md[] = '|---|---:|---|---:|---:|';
foreach ($dbfRows as $row) {
    $name = mdEscape((string) $row['name']);
    $records = $row['records'] === null ? '—' : (string) $row['records'];
    $header = $row['header_type'] ?? '—';
    $dups = $row['duplicates'] === null ? '—' : (string) $row['duplicates'];
    $invalid = $row['invalid'] === null ? '—' : (string) $row['invalid'];
    $md[] = '| `' . $name . '` | ' . $records . ' | ' . mdEscape((string) $header) . ' | ' . $dups . ' | ' . $invalid . ' |';
}
$md[] = '';

$md[] = '## 2) Arquivos CSV, XLS e XLSX';
$md[] = '';
$md[] = '| Nome do arquivo | Tipo do arquivo | Quantidade de registros | Registros duplicados | Estrutura de importação |';
$md[] = '|---|---|---:|---:|---|';
foreach ($tabularRows as $row) {
    $name = mdEscape((string) $row['name']);
    $type = mdEscape((string) ($row['type'] ?? '—'));
    $records = $row['records'] === null ? '—' : (string) $row['records'];
    $dups = $row['duplicates'] === null ? '—' : (string) $row['duplicates'];
    $import = '—';
    if (!empty($row['import_key']) && isset($importLabelByKey[$row['import_key']])) {
        $import = $importLabelByKey[$row['import_key']];
    }
    $md[] = '| `' . $name . '` | ' . $type . ' | ' . $records . ' | ' . $dups . ' | ' . mdEscape($import) . ' |';
}
$md[] = '';

$md[] = '### Definições de importação';
$md[] = '';
if (empty($importDefinitions)) {
    $md[] = '_Nenhuma estrutura de importação foi identificada._';
} else {
    $md[] = '| Estrutura | Regra (tipo + cabeçalho + colunas) | Arquivos |';
    $md[] = '|---|---|---:|';
    foreach ($importDefinitions as $def) {
        $md[] = '| ' . mdEscape($def['label']) . ' | `' . mdEscape($def['key']) . '` | ' . (int) $def['files'] . ' |';
    }
}
$md[] = '';

$md[] = '## Arquivos com erro de leitura';
$md[] = '';
if (empty($dbfErrors) && empty($tabularErrors)) {
    $md[] = '_Nenhum erro de leitura foi detectado._';
} else {
    $md[] = '| Tipo | Arquivo | Erro |';
    $md[] = '|---|---|---|';
    foreach (array_merge($dbfErrors, $tabularErrors) as $e) {
        $md[] = '| ' . mdEscape((string) $e['type']) . ' | `' . mdEscape((string) $e['name']) . '` | ' . mdEscape((string) $e['error']) . ' |';
    }
}
$md[] = '';

$md[] = '## 3) Análise legado vs atual (gaps e riscos)';
$md[] = '';
$md[] = '### Comparações diretas (arquivos)';
$md[] = '';
$md[] = '- `legacy-system/src/upload.php` vs `api/src/upload.php` + `api/src/preview.php`';
$md[] = '- `legacy-system/src/downloadDBF.php` vs `api/src/downloadDBF.php`';
$md[] = '- `legacy-system/src/vendor/csvtodbf/CharSVtoDbf.php` vs `api/vendor/csvtodbf/CharSVtoDbf.php` + `api/src/utils/NativeDbf.php`';
$md[] = '';

$md[] = '### Achados críticos (sem PII)';
$md[] = '';
$md[] = '- **Regra de duplicidade mudou**: no legado, duplicado era basicamente `trim(nome)`; no atual, a chave é **normalizada** (casefold + remoção de acentos + colapso de espaços + truncagem 38). Isso pode transformar nomes antes “diferentes” em iguais (colisão) e gerar risco de **não inserção** ou **atualização inesperada**.';
$md[] = '- **Validação de CSV mais rígida**: o atual exige cabeçalho MedKey (colunas específicas) e pode rejeitar exports fora do padrão.';
$md[] = '- **Fluxo “preview antes de gravar”**: no legado o upload grava direto; no atual existe preview (dry-run) e escrita com cópia de trabalho, reduzindo risco de corrupção — mas qualquer divergência de schema/versão pode quebrar o fluxo.';

if ($currentDbfMeta !== null) {
    $md[] = '- **DBF atual (referência do sistema) — metadados do cabeçalho**: `' . mdEscape($currentDbfMeta['path']) . '` version=' . hexByte((int) $currentDbfMeta['version']) . ', fields=' . (string) ($currentDbfMeta['fields'] ?? '—') . ', record_size=' . (int) $currentDbfMeta['record_size'] . '.';
    if (array_key_exists('size_ok', $currentDbfMeta) && $currentDbfMeta['size_ok'] === false) {
        $md[] = '  - **Integridade suspeita**: file_size=' . (int) ($currentDbfMeta['file_size'] ?? 0) . ' < expected_min_size=' . (int) ($currentDbfMeta['expected_min_size'] ?? 0) . '.';
    }
    if (isset($currentDbfMeta['eof_byte']) && is_int($currentDbfMeta['eof_byte']) && $currentDbfMeta['eof_byte'] !== 0x1A) {
        $md[] = '  - **EOF terminator inesperado**: last_byte=' . hexByte((int) $currentDbfMeta['eof_byte']) . ' (esperado 0x1A).';
    }
}

$md[] = '- **Export/download**: o download atual gera um DBF “do zero” via `CharSVtoDBF` apontando para um arquivo temporário. Se o driver/geração não respeitar o formato esperado do sistema destino, isso pode gerar incompatibilidade.';
$md[] = '';

$md[] = '### Recomendações de ação';
$md[] = '';
$md[] = '- Rodar este relatório sempre que entrar uma nova pasta de dumps/exports antes de repetir testes de importação.';
$md[] = '- Se houver colisões altas de duplicidade após normalização, definir uma regra explícita de desambiguação (ex.: usar CPF quando presente, ou exigir confirmação no preview).';
$md[] = '';

@mkdir(dirname($outputPath), 0777, true);
file_put_contents($outputPath, implode("\n", $md) . "\n");

echo "OK: relatório gerado em {$outputPath}\n";
