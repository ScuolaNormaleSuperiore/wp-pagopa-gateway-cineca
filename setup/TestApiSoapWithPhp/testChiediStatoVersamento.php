<?php

###############  Variabili #####################
$username   = '***';
$password   = '***';
$wsdl       = 'https://gateway.pp.pagoatenei.cineca.it/portalepagamenti.server.gateway/api/private/soap/GPAppPort?wsdl';
$local_cert = __DIR__ . '/sns.it.pem';
$passphrase = '***';
$user_agent = 'Wordpress/PagoPaGatewayCineca';
$cod_app    = '***';

$num_ordine = '100';
#################################################

echo "******************* \n";
$today = date( 'd/m/Y Y h:i:s A' );
echo "\n \n" . 'Data di oggi: ' . $today . "\n";
// echo phpinfo();
echo 'Openssl attivo? ', extension_loaded ('openssl' ) ? 'yes' : 'no', "\n";
echo 'SOAP attivo? ', extension_loaded ('soap' ) ? 'yes' : 'no', "\n";
echo 'Certificato presente?',  file_exists($local_cert) ? 'yes' : 'no', "\n";
echo 'File del certificato:' . $local_cert . "\n \n";
echo "******************* \n";
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
	'local_cert'         => $local_cert,
	'passphrase'         => $passphrase,
	'stream_context'     => stream_context_create( $context_options ),
	'soap_version'       => SOAP_1_1,
);

$soap_client = null;
try {
	$soap_client = new SoapClient(
		$wsdl,
		$soap_client_options,
	);
	echo '<BR/><BR/>Metodi disponibili:<BR/><pre>' . var_export( $soap_client->__getFunctions(), true ) . '</pre>';
} catch ( Exception $e ) {
	echo '@@@ ERRORE CLIENT--->' . $e->getMessage();
}


###############  PREPARAZIONE E INVIO CHIAMATA SOAP #####################
if ( $soap_client ) {

	$bodyrichiesta    = array(
		'codApplicazione'  => $cod_app,
		'codVersamentoEnte' => $num_ordine,
	);

	try {
		$result = $soap_client->gpChiediStatoVersamento( $bodyrichiesta );
		# Mostra risultati.
		echo '<BR/>========= RESULT ==========<BR/>';
		var_export( $result );
		echo '<BR/><BR/><BR/>*** TEST ESEGUITO CORRETTAMENTE ***';
	} catch ( Exception $e ) {
		echo '@@@ ERRORE CHIAMATA --->' . $e->getMessage() . '<BR/>';
		echo '<BR/>====== REQUEST HEADERS ===== <BR/>';
		// var_export($soap_client);
		var_export( $soap_client->__getLastRequestHeaders() );
		echo '<BR/>========= REQUEST ==========<BR/>';
		var_export( $soap_client->__getLastRequest() );
		echo '<br/>Debug autenticazione: ' . base64_encode( $username . ':' . $password ) . '<BR/>';
		echo '<BR/><BR/><BR/>*** TEST FALLITO ***';
	}
}

?>
