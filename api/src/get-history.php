<?php

/**
 * Vogel / Dataweaver
 * Endpoint to serve import history from JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/utils/Cors.php';
require_once __DIR__ . '/utils/ImportHistory.php';
\Vogel\Utils\Cors::apply();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$projectRoot = dirname(__DIR__);
$historyFile = $projectRoot . '/database/import_history.json';
require_once __DIR__ . '/utils/Auth.php';

$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

$history = \Vogel\Utils\ImportHistory::read($historyFile);

echo json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
