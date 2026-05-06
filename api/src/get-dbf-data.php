<?php

/**
 * Dataweaver - Get DBF Data
 * Paginated and Searchable list of patients from RELAT_orto.DBF
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$srcDir = __DIR__;
$projectRoot = dirname($srcDir);

require_once $srcDir . '/utils/NativeDbf.php';
require_once $srcDir . '/utils/TextEncoding.php';
require_once $srcDir . '/utils/Auth.php';
require_once $srcDir . '/utils/Cors.php';

header('Content-Type: application/json');
\Vogel\Utils\Cors::apply();
$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

try {
    $dbfPath = $projectRoot . '/database/RELAT_orto.DBF';
    
    // Check if file exists (Windows/Linux case sensitivity check)
    if (!file_exists($dbfPath)) {
        // Fallback for common case issues
        $alternatives = [
            $projectRoot . '/database/relat_orto.dbf',
            $projectRoot . '/database/RELAT_ORTO.DBF',
            $projectRoot . '/database/RELAT_ORTO.dbf'
        ];
        foreach ($alternatives as $alt) {
            if (file_exists($alt)) {
                $dbfPath = $alt;
                break;
            }
        }
    }

    if (!file_exists($dbfPath)) {
        throw new Exception("Banco de dados não encontrado: RELAT_orto.DBF");
    }

    $dbf = new \Vogel\Utils\NativeDbf($dbfPath, 0); // Read-only
    $totalRecords = $dbf->getNumRecords();
    
    // Sort/Pagination params
    $page    = isset($_GET['page'])    ? (int)$_GET['page']     : 1;
    $limit   = isset($_GET['limit'])   ? (int)$_GET['limit']    : 50;
    $search  = isset($_GET['q'])       ? trim($_GET['q'])       : '';
    $sortBy  = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'id';
    $order   = isset($_GET['order'])   ? strtolower($_GET['order']) : 'desc';

    $collator = class_exists('\\Collator') ? new \Collator('pt_BR') : null;
    if ($collator instanceof \Collator) {
        $collator->setAttribute(\Collator::CASE_LEVEL, \Collator::OFF);
        $collator->setStrength(\Collator::SECONDARY);
    }

    $searchKey = $search !== '' ? \Vogel\Utils\TextEncoding::normalizeForSearch($search) : '';
    
    $allMatches = [];
    
    // Collect all records that match search (or all if no search)
    // We iterate reverse by default as it's the most common use case for "newest first"
    for ($i = $totalRecords; $i >= 1; $i--) {
        $record = $dbf->getRecord($i);
        if (!$record || $record['deleted']) continue;
        
        $nome = $record[1];
        
        if (!empty($search)) {
            if (strpos(\Vogel\Utils\TextEncoding::normalizeForSearch($nome), $searchKey) === false) {
                continue;
            }
        }
        
        // Map to UTF-8 and normalize visible text only for display/search.
        $cleanRecord = $record;
        foreach ([1 => 'nome', 2 => 'responsavel', 4 => 'localidade', 5 => 'uf', 6 => 'cep', 7 => 'cpf'] as $idx => $fieldName) {
            if (isset($cleanRecord[$idx]) && is_string($cleanRecord[$idx])) {
                $fieldReport = [];
                $cleanRecord[$idx] = \Vogel\Utils\TextEncoding::sanitizeImportValue($cleanRecord[$idx], $fieldName, $fieldReport);
            }
        }
        foreach ($cleanRecord as $key => $val) {
            if (is_string($val)) {
                $cleanRecord[$key] = trim($val);
            }
        }

        // Add index/id for internal sorting reference
        $cleanRecord['_db_index'] = $i;
        $allMatches[] = $cleanRecord;
    }

    $matchedCount = count($allMatches);

    // Apply Sorting
    usort($allMatches, function($a, $b) use ($sortBy, $order, $collator) {
        $valA = '';
        $valB = '';

        switch ($sortBy) {
            case 'name':
                $valA = $a[1] ?? '';
                $valB = $b[1] ?? '';
                break;
            case 'responsible':
                $valA = $a[2] ?? '';
                $valB = $b[2] ?? '';
                break;
            case 'location':
                $valA = trim(($a[4] ?? '') . ' ' . ($a[5] ?? ''));
                $valB = trim(($b[4] ?? '') . ' ' . ($b[5] ?? ''));
                break;
            case 'id':
            default:
                $valA = (int)($a[0] ?? 0);
                $valB = (int)($b[0] ?? 0);
                break;
        }

        if ($valA == $valB) {
            return 0;
        }

        if ($sortBy !== 'id') {
            $valA = (string) $valA;
            $valB = (string) $valB;

            if ($collator instanceof \Collator) {
                $result = $collator->compare($valA, $valB);
                if ($result === 0) {
                    $result = strcmp($valA, $valB);
                }
            } else {
                $result = strcmp($valA, $valB);
            }
        } else {
            $result = ($valA <=> $valB);
        }

        if ($order === 'desc') {
            $result *= -1;
        }

        return $result;
    });

    // Pagination Slice
    $start = ($page - 1) * $limit;
    $pagedData = array_slice($allMatches, $start, $limit);
    
    echo json_encode([
        'status' => 'success',
        'data' => $pagedData,
        'total' => $matchedCount,
        'page' => $page,
        'limit' => $limit,
        'sort_by' => $sortBy,
        'order' => $order
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
