<?php
require_once __DIR__ . '/utils/NativeDbf.php';
require_once __DIR__ . '/utils/Auth.php';

header('Content-Type: text/plain');

$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

try {
    $dbfPath = dirname(__DIR__) . '/database/RELAT_orto.DBF';
    // Fallback logic
    if (!file_exists($dbfPath)) {
        foreach ([dirname(__DIR__) . '/database/relat_orto.dbf', dirname(__DIR__) . '/database/RELAT_ORTO.DBF'] as $alt) {
            if (file_exists($alt)) { $dbfPath = $alt; break; }
        }
    }

    if (!file_exists($dbfPath)) die("DBF NOT FOUND: " . $dbfPath);

    $dbf = new \Vogel\Utils\NativeDbf($dbfPath);
    echo "DBF Structure Info:\n";
    echo "Path: $dbfPath\n";
    echo "Total Records: " . $dbf->getNumRecords() . "\n\n";

    // Access private property fields via reflection or just read header manually
    $reflection = new ReflectionClass($dbf);
    $fieldsProp = $reflection->getProperty('fields');
    $fieldsProp->setAccessible(true);
    $fields = $fieldsProp->getValue($dbf);

    echo "Field List:\n";
    foreach ($fields as $i => $f) {
        echo "[$i] Name: " . $f['name'] . " | Type: " . $f['type'] . " | Len: " . $f['length'] . "\n";
    }

    echo "\nSample Record (index 1):\n";
    $record = $dbf->getRecord(1);
    if ($record) {
        foreach ($fields as $i => $f) {
            $val = $record[$i];
            echo "[$i] " . $f['name'] . ": " . $val . "\n";
        }
    } else {
        echo "No records found or error reading index 1.";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
