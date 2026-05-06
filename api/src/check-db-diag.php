<?php

/**
 * Dataweaver - Remote Database Diagnostics
 * Help the user identify why the DB is not being read.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/utils/Auth.php';
$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

echo "--- Dataweaver Remote DB Diagnostics ---\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
$userName = function_exists('posix_getpwuid') && function_exists('posix_geteuid') 
    ? posix_getpwuid(posix_geteuid())['name'] 
    : 'Unknown (POSIX disabled)';
echo "PHP User: " . get_current_user() . " ($userName)\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "\n";

$srcDir = __DIR__;
$projectRoot = dirname($srcDir);
$dbPath = $projectRoot . '/database/RELAT_orto.DBF';

echo "1. Checking Paths:\n";
echo "   srcDir: $srcDir\n";
echo "   projectRoot: $projectRoot\n";
echo "   Target DB Path: $dbPath\n";
echo "\n";

echo "2. Checking Directory Permissions:\n";
$dirs = [
    $projectRoot . '/database',
    $projectRoot . '/uploads',
    $projectRoot . '/backup',
    $projectRoot . '/logs'
];

foreach ($dirs as $dir) {
    echo "   Dir: " . basename($dir) . "\n";
    if (is_dir($dir)) {
        echo "      Exists: YES\n";
        echo "      Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";
        echo "      Readable: " . (is_readable($dir) ? 'YES' : 'NO') . "\n";
        echo "      Writable: " . (is_writable($dir) ? 'YES' : 'NO') . "\n";
        
        // List files in directory
        echo "      Files: " . implode(', ', array_diff(scandir($dir), ['.', '..'])) . "\n";
    } else {
        echo "      Exists: NO (!!!)\n";
    }
}
echo "\n";

echo "3. File check (RELAT_orto.DBF):\n";
if (file_exists($dbPath)) {
    echo "   Exists: YES\n";
    echo "   Size: " . round(filesize($dbPath) / 1024 / 1024, 2) . " MB\n";
    echo "   Readable: " . (is_readable($dbPath) ? 'YES' : 'NO') . "\n";
    echo "   Writable: " . (is_writable($dbPath) ? 'YES' : 'NO') . "\n";
    $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($dbPath))['name'] : 'Unknown';
    echo "   Owner: " . $owner . "\n";
} else {
    echo "   Exists: NO (!!!)\n";
    
    // Case sensitivity test
    echo "   Case search result:\n";
    $files = scandir($projectRoot . '/database');
    foreach ($files as $f) {
        if (stripos($f, 'relat_orto') !== false) {
            echo "      Found similar: $f\n";
        }
    }
}
echo "\n";

echo "4. Runtime Check (NativeDbf):\n";
require_once $srcDir . '/utils/NativeDbf.php';
try {
    $dbf = new \Vogel\Utils\NativeDbf($dbPath, 0);
    echo "   NativeDbf initialized: SUCCESS\n";
    echo "   Records found: " . $dbf->getNumRecords() . "\n";
} catch (Exception $e) {
    echo "   NativeDbf Error: " . $e->getMessage() . "\n";
}

echo "\n--- End of Diag ---\n";
