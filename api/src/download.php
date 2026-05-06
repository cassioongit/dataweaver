<?php

require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/utils/Cors.php';

\Vogel\Utils\Cors::apply();

$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

$filepath = __DIR__ . '/../database/RELAT_orto.DBF';

if (file_exists($filepath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    flush(); 
    readfile($filepath);
    exit;
} else {
    http_response_code(404);
    echo "Base de dados não encontrada.";
}
