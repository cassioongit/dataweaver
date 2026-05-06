<?php
/**
 * DBF audit (read-only) for Dataweaver.
 *
 * Usage:
 *   php automation/dbf-audit.php [path/to/file.DBF]
 *
 * Defaults to api/database/RELAT_orto.DBF
 */

declare(strict_types=1);

function fail(string $msg, int $code = 1): void {
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function fmtBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $v = (float) $bytes;
    while ($v >= 1024 && $i < count($units) - 1) {
        $v /= 1024;
        $i++;
    }
    return sprintf('%.2f %s', $v, $units[$i]);
}

function hexByte(int $b): string {
    return '0x' . strtoupper(str_pad(dechex($b & 0xFF), 2, '0', STR_PAD_LEFT));
}

function isAsciiControl(int $b): bool {
    // allow tab, lf, cr
    if ($b === 0x09 || $b === 0x0A || $b === 0x0D) return false;
    return $b >= 0x00 && $b < 0x20;
}

function maskCpf(string|int $cpf): string {
    $digits = preg_replace('/\D+/', '', (string) $cpf);
    if (strlen($digits) !== 11) {
        return '***.***.***-**';
    }
    return substr($digits, 0, 3) . '.***.***-' . substr($digits, -2);
}

$projectRoot = realpath(__DIR__ . '/..') ?: getcwd();
$defaultPath = $projectRoot . '/api/database/RELAT_orto.DBF';
$pathArg = $argv[1] ?? $defaultPath;
$dbfPath = realpath($pathArg);
if (!$dbfPath || !is_file($dbfPath)) {
    fail("DBF nao encontrado: " . $pathArg);
}

$fh = fopen($dbfPath, 'rb');
if (!$fh) fail("Nao foi possivel abrir: " . $dbfPath);

$fileSize = filesize($dbfPath);
if (!is_int($fileSize) || $fileSize <= 0) {
    fail("Nao foi possivel obter tamanho do arquivo: " . $dbfPath);
}

$hdrRaw = fread($fh, 32);
if ($hdrRaw === false || strlen($hdrRaw) < 32) {
    fail("Header invalido (menos de 32 bytes).");
}

$hdr = unpack('Cversion/Cyy/Cmm/Cdd/VnumRecords/vheaderSize/vrecordSize', $hdrRaw);
if (!is_array($hdr)) fail("Falha ao ler header.");

$version = (int) $hdr['version'];
$yy = (int) $hdr['yy'];
$mm = (int) $hdr['mm'];
$dd = (int) $hdr['dd'];
$numRecords = (int) $hdr['numRecords'];
$headerSize = (int) $hdr['headerSize'];
$recordSize = (int) $hdr['recordSize'];

$isDbase3 = ($version === 0x03);
$descStart = $isDbase3 ? 32 : 68;
$descSize = $isDbase3 ? 32 : 48;

echo "DBF Audit Report\n";
echo "Path: {$dbfPath}\n";
echo "Size: " . fmtBytes($fileSize) . " ({$fileSize} bytes)\n";
echo "Header.version: " . hexByte($version) . ($isDbase3 ? " (DBASE III)\n" : " (NOT DBASE III)\n");
$year = 1900 + $yy;
echo "Header.last_update: " . sprintf('%04d-%02d-%02d', $year, $mm, $dd) . "\n";
echo "Header.num_records: {$numRecords}\n";
echo "Header.header_size: {$headerSize}\n";
echo "Header.record_size: {$recordSize}\n";
echo "\n";

// Basic header consistency
$expectedMinSize = $headerSize + ($numRecords * $recordSize) + 1;
echo "Integrity\n";
echo "- Expected minimum file size: {$expectedMinSize} bytes\n";
echo "- Actual file size: {$fileSize} bytes\n";
echo "- Size OK: " . (($fileSize >= $expectedMinSize) ? "YES" : "NO") . "\n";

// Header terminator 0x0D
fseek($fh, max(0, $headerSize - 1));
$terminator = fread($fh, 1);
$termByte = ($terminator !== false && strlen($terminator) === 1) ? ord($terminator) : -1;
echo "- Header terminator @headerSize-1 == 0x0D: " . (($termByte === 0x0D) ? "YES" : ("NO (" . ($termByte >= 0 ? hexByte($termByte) : "missing") . ")")) . "\n";

// EOF 0x1A
fseek($fh, $fileSize - 1);
$eof = fread($fh, 1);
$eofByte = ($eof !== false && strlen($eof) === 1) ? ord($eof) : -1;
echo "- EOF terminator last byte == 0x1A: " . (($eofByte === 0x1A) ? "YES" : ("NO (" . ($eofByte >= 0 ? hexByte($eofByte) : "missing") . ")")) . "\n";

if ($headerSize < $descStart + 1) {
    echo "- Field descriptor area: INVALID (header too small)\n\n";
    fclose($fh);
    exit(0);
}

$rawFieldBytes = $headerSize - $descStart - 1;
$numFieldsFloat = $rawFieldBytes / $descSize;
$numFields = (int) floor($numFieldsFloat + 1e-9);
$fieldsOk = (abs($numFieldsFloat - $numFields) < 1e-6);
echo "- Fields descriptor sizing: " . ($fieldsOk ? "OK" : "SUSPECT") . " (computed fields={$numFieldsFloat})\n";
echo "\n";

// Parse fields
fseek($fh, $descStart);
$fields = [];
$offset = 1; // deletion flag
$sumLengths = 0;
for ($i = 0; $i < $numFields; $i++) {
    $raw = fread($fh, $descSize);
    if ($raw === false || strlen($raw) < $descSize) {
        break;
    }

    $nameLen = $isDbase3 ? 11 : 32;
    $name = trim(substr($raw, 0, $nameLen), "\0 ");
    $typeOffset = $isDbase3 ? 11 : 32;
    $type = substr($raw, $typeOffset, 1);
    $lenOffset = $isDbase3 ? 16 : 33;
    $len = ord(substr($raw, $lenOffset, 1));

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
echo "Schema\n";
echo "- Fields: " . count($fields) . "\n";
echo "- Computed record_size from fields: {$computedRecordSize} (header says {$recordSize})\n";
echo "- Record size OK: " . (($computedRecordSize === $recordSize) ? "YES" : "NO") . "\n";

// Identify likely keys
$idxNCAD = 0;
foreach ($fields as $f) {
    if (preg_match('/\bNCAD\b/i', $f['name'])) { $idxNCAD = $f['index']; break; }
}
$idxCPF = null;
foreach ($fields as $f) {
    if (preg_match('/\bCPF\b/i', $f['name'])) { $idxCPF = $f['index']; break; }
}
if ($idxCPF === null) {
    foreach ($fields as $f) {
        if (preg_match('/\bDOC\b|\bDOCUMENTO\b/i', $f['name'])) { $idxCPF = $f['index']; break; }
    }
}

echo "- Key fields (heuristic): NCAD idx={$idxNCAD}";
echo $idxCPF !== null ? (", CPF/DOC idx={$idxCPF}\n") : (", CPF/DOC idx=NOT FOUND\n");
echo "\n";

// Build quick lookup for offsets by index
$fieldByIndex = [];
foreach ($fields as $f) {
    $fieldByIndex[$f['index']] = $f;
}

// Audit records
echo "Data Scan\n";

$deletedCount = 0;
$emptyNameCount = 0;
$emptyNcadCount = 0;

$ncadSeen = [];
$ncadDup = [];
$cpfSeen = [];
$cpfDup = [];

$undefinedCp1252Bytes = [0x81, 0x8D, 0x8F, 0x90, 0x9D];
$counts = [
    'null_bytes' => 0,
    'control_bytes' => 0,
    'undefined_cp1252' => 0,
    'likely_utf8_sequences' => 0,
];
$sampleWeird = [];

// Decide which fields to scan for weird bytes: all character-ish fields
$scanFields = [];
foreach ($fields as $f) {
    if (in_array($f['type'], ['C', 'M', 'G', 'V', 'W'], true)) {
        $scanFields[] = $f;
    }
}

$recordsToScan = $numRecords;
// Safety: cap scanning to avoid accidental huge file lockups
if ($recordsToScan > 200000) {
    $recordsToScan = 200000;
}

for ($recNo = 1; $recNo <= $recordsToScan; $recNo++) {
    $pos = $headerSize + (($recNo - 1) * $recordSize);
    if ($pos + $recordSize > $fileSize) {
        break;
    }
    fseek($fh, $pos);
    $rawRec = fread($fh, $recordSize);
    if ($rawRec === false || strlen($rawRec) < $recordSize) {
        break;
    }

    $deleted = ($rawRec[0] === '*');
    if ($deleted) {
        $deletedCount++;
        continue;
    }

    // NCAD duplicate scan
    if (isset($fieldByIndex[$idxNCAD])) {
        $f = $fieldByIndex[$idxNCAD];
        $bytes = substr($rawRec, $f['offset'], $f['length']);
        $val = trim($bytes);
        // If type O (8-byte) or numeric-ish, just treat as raw bytes -> hex/int best-effort
        if ($f['type'] === 'O' && strlen($bytes) === 8) {
            $un = unpack('P', $bytes);
            $val = (string) ($un[1] ?? 0);
        } else {
            $val = trim(mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252'));
        }

        if ($val === '' || $val === '0') {
            $emptyNcadCount++;
        } else {
            if (isset($ncadSeen[$val])) {
                $ncadDup[$val] = ($ncadDup[$val] ?? 1) + 1;
            } else {
                $ncadSeen[$val] = true;
            }
        }
    }

    // CPF duplicates scan
    if ($idxCPF !== null && isset($fieldByIndex[$idxCPF])) {
        $f = $fieldByIndex[$idxCPF];
        $bytes = substr($rawRec, $f['offset'], $f['length']);
        $val = trim(mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252'));
        $digits = preg_replace('/\D+/', '', $val);
        if ($digits !== '' && strlen($digits) >= 8) {
            if (isset($cpfSeen[$digits])) {
                $cpfDup[$digits] = ($cpfDup[$digits] ?? 1) + 1;
            } else {
                $cpfSeen[$digits] = true;
            }
        }
    }

    // Empty name heuristic: if there's a field named PACIENTE or NOME
    foreach ($fields as $f) {
        if (preg_match('/PACIENTE|NOME/i', $f['name'])) {
            $bytes = substr($rawRec, $f['offset'], $f['length']);
            $val = trim(mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252'));
            if ($val === '') $emptyNameCount++;
            break;
        }
    }

    // Weird byte scan
    foreach ($scanFields as $f) {
        $bytes = substr($rawRec, $f['offset'], $f['length']);
        if ($bytes === '') continue;

        $len = strlen($bytes);
        for ($k = 0; $k < $len; $k++) {
            $b = ord($bytes[$k]);
            if ($b === 0x00) {
                $counts['null_bytes']++;
                if (count($sampleWeird) < 8) $sampleWeird[] = ['rec' => $recNo, 'field' => $f['name'], 'kind' => 'null_byte'];
                break;
            }
            if (isAsciiControl($b)) {
                $counts['control_bytes']++;
                if (count($sampleWeird) < 8) $sampleWeird[] = ['rec' => $recNo, 'field' => $f['name'], 'kind' => 'control_byte:' . hexByte($b)];
                break;
            }
            if (in_array($b, $undefinedCp1252Bytes, true)) {
                $counts['undefined_cp1252']++;
                if (count($sampleWeird) < 8) $sampleWeird[] = ['rec' => $recNo, 'field' => $f['name'], 'kind' => 'undefined_cp1252:' . hexByte($b)];
                break;
            }
        }

        // Heuristic: likely UTF-8 bytes stored in a CP1252 DBF (mojibake risk)
        // Detect common two-byte sequences starting with 0xC3 or 0xC2.
        if (preg_match('/\xC3[\x80-\xBF]|\xC2[\x80-\xBF]/', $bytes) === 1) {
            $counts['likely_utf8_sequences']++;
            if (count($sampleWeird) < 8) $sampleWeird[] = ['rec' => $recNo, 'field' => $f['name'], 'kind' => 'likely_utf8_bytes'];
        }
    }
}

$dupNcadCount = count($ncadDup);
$dupCpfCount = count($cpfDup);

echo "- Records scanned: {$recordsToScan}\n";
echo "- Deleted records skipped: {$deletedCount}\n";
echo "- Empty NCAD (heuristic): {$emptyNcadCount}\n";
echo "- Empty Name (heuristic): {$emptyNameCount}\n";
echo "\n";

echo "Duplicates\n";
echo "- NCAD duplicates: {$dupNcadCount}\n";
if ($dupNcadCount > 0) {
    arsort($ncadDup);
    $top = array_slice($ncadDup, 0, 10, true);
    echo "  Top NCAD duplicates (up to 10):\n";
    foreach ($top as $id => $count) {
        echo "  - {$id} x{$count}\n";
    }
}
echo "- CPF/DOC duplicates: {$dupCpfCount}\n";
if ($dupCpfCount > 0) {
    arsort($cpfDup);
    $top = array_slice($cpfDup, 0, 10, true);
    echo "  Top CPF/DOC duplicates (up to 10):\n";
    foreach ($top as $digits => $count) {
        echo "  - " . maskCpf($digits) . " x{$count}\n";
    }
}
echo "\n";

echo "Encoding / Strange Bytes (heuristics)\n";
echo "- Null bytes inside character fields: {$counts['null_bytes']}\n";
echo "- ASCII control bytes inside character fields: {$counts['control_bytes']}\n";
echo "- Undefined Windows-1252 bytes (81/8D/8F/90/9D): {$counts['undefined_cp1252']}\n";
echo "- Likely UTF-8 byte sequences stored in char fields: {$counts['likely_utf8_sequences']}\n";
if (!empty($sampleWeird)) {
    echo "  Samples (record#, field, kind):\n";
    foreach ($sampleWeird as $s) {
        echo "  - #{$s['rec']} {$s['field']} {$s['kind']}\n";
    }
}
echo "\n";

echo "Notes\n";
echo "- This audit does not modify the DBF.\n";
echo "- Duplicate checks are heuristic (NCAD + CPF/DOC) based on detected schema.\n";
echo "- Encoding checks flag suspicious bytes; they do not prove corruption by themselves.\n";

fclose($fh);
