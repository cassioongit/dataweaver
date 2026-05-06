<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('America/Sao_Paulo');

$srcDir = __DIR__;
$projectRoot = dirname($srcDir);

require_once $srcDir . '/utils/Auth.php';
require_once $srcDir . '/utils/Cors.php';

header('Content-Type: application/json');
\Vogel\Utils\Cors::apply();

$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: '{}', true);
if (!is_array($payload)) {
    $payload = [];
}

$deleted = [];

$auditFile = $payload['audit_file'] ?? '';
if (is_string($auditFile) && $auditFile !== '') {
    $auditFile = ltrim($auditFile, '/');
    if (strpos($auditFile, 'logs/import_audits/') === 0) {
        $fullPath = $projectRoot . '/' . $auditFile;
        if (is_file($fullPath)) {
            @unlink($fullPath);
            $deleted[] = $auditFile;
        }
    }
}

$latestPreview = $projectRoot . '/logs/import_audits/latest_preview.json';
if (is_file($latestPreview)) {
    @unlink($latestPreview);
    $deleted[] = 'logs/import_audits/latest_preview.json';
}

$fileName = $payload['file_name'] ?? '';
if (is_string($fileName) && $fileName !== '') {
    $tempPreview = $projectRoot . '/uploads/preview_' . basename($fileName);
    if (is_file($tempPreview)) {
        @unlink($tempPreview);
        $deleted[] = 'uploads/' . basename($tempPreview);
    }
}

echo json_encode([
    'status' => 'ok',
    'deleted' => $deleted,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
