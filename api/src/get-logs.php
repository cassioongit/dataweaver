<?php

/**
 * Vogel / Conciliação Digital
 * Endpoint to serve system logs as JSON
 */

$srcDir = __DIR__;
require_once $srcDir . '/utils/Logger.php';
require_once $srcDir . '/utils/Auth.php';
require_once $srcDir . '/utils/Cors.php';

header('Content-Type: application/json');
\Vogel\Utils\Cors::apply();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logger = \Vogel\Utils\Logger::getInstance();
$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);
$level = $_GET['level'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$logs = $logger->getLogs($limit, $level);

echo json_encode($logs);
