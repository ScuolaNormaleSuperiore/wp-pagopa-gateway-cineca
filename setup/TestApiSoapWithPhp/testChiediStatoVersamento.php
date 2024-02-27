<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$OS_SEPARATOR = "";
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	$OS_SEPARATOR = "\\";
} else {
	$OS_SEPARATOR = "/";
}

###############  Variabili #####################
$username   = '***';
$password   = '***';
$wsdl       = 'https://gateway.pp.pagoatenei.cineca.it/portalepagamenti.server.gateway/api/private/soap/GPAppPort?wsdl';
$local_cert = __DIR__ . '/sns.it.pem';
$passphrase = '***';
$user_agent = 'Wordpress/PagoPaGatewayCineca';
$cod_app    = '***';
$soap_tmout = 20;

$num_ordine = '100';
#################################################

echo "\n *******************";
$today = date('d/m/Y Y h:i:s A');
echo "\n \n" . 'Data di oggi: ' . $today . "\n";
// echo phpinfo();
echo 'Sistema operativo? ', PHP_OS, "\n";
echo 'Openssl attivo? ', extension_loaded('openssl') ? 'yes' : 'no', "\n";
echo 'Openssl versione: ', OPENSSL_VERSION_TEXT, " - ", OPENSSL_VERSION_NUMBER, "\n";
echo 'SOAP attivo? ', extension_loaded('soap') ? 'yes' : 'no', "\n";
echo 'Certificato presente? ',  file_exists($local_cert) ? 'yes' : 'no', "\n";
echo 'File del certificato:' . $local_cert . "\n";
echo "******************* \n \n";
###############  CREAZIONE CONNESSIONE SOAP #####################

// set some SSL/TLS specific options .
$context_options = array(
	'ssl' => array(
		'verify_peer'       => false,
		'verify_host'       => false,
		'verify_peer_name'  => false,
		'allow_self_signed' => true,
		'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
	),
);

$soap_client_options = array(
	'user_agent'         => $user_agent,
	'login'              => $username,
	'password'           => $password,
	'authentication'     => SOAP_AUTHENTICATION_BASIC,
	'exception'          => true,
	'keep_alive '        => false,
	'encoding'           => 'UTF-8',
	'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
	'cache_wsdl'         => WSDL_CACHE_NONE,
	'trace'              => true,
	'connection_timeout' => intval($soap_tmout),
	'local_cert'         => $local_cert,
	'passphrase'         => $passphrase,
	'stream_context'     => stream_context_create($context_options),
	'soap_version'       => SOAP_1_1,
);

$soap_client = null;
try {
	$soap_client = new SoapClient(
		$wsdl,
		$soap_client_options,
	);
	echo "\nMetodi disponibili: \n" . var_export($soap_client->__getFunctions(), true) . "\n";
	echo "******************* \n";
} catch (Exception $e) {
	echo '@@@ ERRORE CLIENT--->' . $e->getMessage();
	echo "******************* \n";
	exit;
}


###############  PREPARAZIONE E INVIO CHIAMATA SOAP #####################
if ($soap_client) {

	$bodyrichiesta    = array(
		'codApplicazione'  => $cod_app,
		'codVersamentoEnte' => $num_ordine,
	);

	try {
		$result = $soap_client->gpChiediStatoVersamento($bodyrichiesta);
		# Mostra risultati.
		echo "\n========= RESULT ==========\n";
		var_export($result);
		echo "\n*** TEST ESEGUITO CORRETTAMENTE ***\n\n";
	} catch (Exception $e) {
		echo '@@@ ERRORE CHIAMATA --->' . $e->getMessage() . "\n";
		echo "\n====== REQUEST HEADERS ===== \n";
		// var_export($soap_client);
		var_export($soap_client->__getLastRequestHeaders());
		echo "\n========= REQUEST ==========\n";
		var_export($soap_client->__getLastRequest());
		echo "\nDebug autenticazione: " . base64_encode($username . ':' . $password) . "\n";
		echo "\n*** TEST FALLITO ***\n\n";
	}
}
