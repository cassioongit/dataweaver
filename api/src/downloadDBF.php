<?php

require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/utils/Cors.php';
require_once __DIR__ . '/../vendor/csvtodbf/CharSVtoDbf.php';

\Vogel\Utils\Cors::apply();

$currentUser = \Vogel\Utils\Auth::requireAuthenticatedUser();
unset($currentUser);

$projectRoot = dirname(__DIR__);
$filepath = tempnam($projectRoot . '/database', 'RELAT_orto_export_');

if ($filepath === false) {
	http_response_code(500);
	echo json_encode(['status' => 'error', 'erro' => 'Não foi possível criar o arquivo temporário de exportação.']);
	exit;
}

$x = new CharSVtoDBF($filepath,
	array(
		  array('NºCad','N',5,0),
		  array('Nome do Pa','C',38),
		  array('Resp.Pagam','C',33),
		  array('Endereço(','C',38),
		  array('Cidade (Re','C',25),
		  array('UF(Resid) ','C',2),
		  array('CEP (Resid','C',9),
		  array('CPF Resp.P','C',15),
		  array('Endereço(','C',38),
		  array('Cidade (Co','C',25),
		  array('UF (Com)','C',2),
		  array('CEP (Com)','C',9),
		  array('envio bole','C',20),
		  array('DR/DRA','C',20)
		),
	2
	);

$x->toDBF('csv.txt',',');
$x->closeDbase();


// Process download
if(file_exists($filepath)) {
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="RELAT_orto.DBF"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($filepath));
	flush(); // Flush system output buffer
	readfile($filepath);
	@unlink($filepath);
	exit;
}

@unlink($filepath);
