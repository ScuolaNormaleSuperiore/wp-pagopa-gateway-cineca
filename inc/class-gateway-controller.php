<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     0.0.1
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

define( 'PATH_WSDL_CINECA', '/portalepagamenti.server.gateway/api/private/soap/GPAppPort?wsdl' );
define( 'PATH_FRONT_END_CINECA', '/portalepagamenti.server.frontend/#/ext' );
define( 'PAR_SPLITTER', '||' );

/**
 * Gateway_Controller class
 */
class Gateway_Controller {

	/**
	 * Create the Gateway controller.
	 *
	 * @param WP_Gateway_PagoPa $plugin - The payment plugin.
	 */
	public function __construct( $plugin ) {
		$this->plugin  = $plugin;
		$this->ws_data = array();

		if ( 'yes' === $this->plugin->settings['testmode'] ) {
			// Get the parameters of the TEST configutation .
			$this->ws_data['frontend_base_url'] = trim( $this->plugin->settings['base_fronted_url_test'], '/' );
			$this->ws_data['ws_soap_base_url']  = trim( $this->plugin->settings['base_url_test'], '/' );
			$this->ws_data['ws_username']       = $this->plugin->settings['username_test'];
			$this->ws_data['ws_password']       = $this->plugin->settings['password_test'];
		} else {
			// Get the parameters of the PRODUCTION configutation .
			$this->ws_data['frontend_base_url'] = trim( $this->plugin->settings['base_fronted_url_prod'], '/' );
			$this->ws_data['ws_soap_base_url']  = trim( $this->plugin->settings['base_url_prod'], '/' );
			$this->ws_data['ws_username']       = $this->plugin->settings['username_prod'];
			$this->ws_data['ws_password']       = $this->plugin->settings['password_prod'];
		}
	}

	/**
	 * Init the controller
	 *
	 * @param WC_Order $order - The e-commerce order.
	 * @return void
	 */
	public function init( $order ) {
		$this->order      = $order;
		$this->local_cert = $this->plugin->settings['cert_abs_path'];
		$this->passphrase = $this->plugin->settings['cert_passphrase'];

		// set some SSL/TLS specific options .
		$context_options = array(
			'ssl' => array(
				'verify_peer'       => false,
				'verify_host'       => false,
				'verify_peer_name'  => false,
				'allow_self_signed' => true,
			),
		);

		$this->wsdl_url      = $this->ws_data['ws_soap_base_url'] . PATH_WSDL_CINECA;
		$soap_client_options = array(
			'user_agent'         => 'Wordpress/PagoPaGatewayCineca',
			'login'              => $this->ws_data['ws_username'],
			'password'           => $this->ws_data['ws_password'],
			'exception'          => true,
			'encoding'           => 'UTF-8',
			'location'           => $this->wsdl_url,
			'cache_wsdl'         => WSDL_CACHE_NONE,
			'trace'              => true,
			'exceptions'         => false,
			'connection_timeout' => 30,
			'local_cert'         => $this->local_cert,
			'passphrase'         => $this->passphrase,
			'stream_context'     => stream_context_create( $context_options ),
			'crypto_method'      => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
			'soap_version'       => SOAP_1_1,
		);

		$this->soap_client = new SoapClient(
			$this->wsdl_url,
			$soap_client_options,
		);
	}


	/**
	 * Execute the SOAP call to load payment position on the gateway.
	 *
	 * @return array
	 */
	public function load_payment_position() {
		$expiration_date = gmdate( 'Y-m-d\TH:i:s', strtotime( '3 hour' ) );
		$bodyrichiesta   = array(
			'generaIuv'        => true,
			'aggiornaSeEsiste' => true,
			'versamento'       => array(
				'codApplicazione'    => $this->plugin->settings['application_code'],
				'codVersamentoEnte'  => $this->order->get_order_number(),
				'codDominio'         => $this->plugin->settings['domain_code'],
				'debitore'           => array(
					'codUnivoco'     => $this->formatString( $this->order->get_meta( '_billing_ita_cf' ) ),
					'ragioneSociale' => $this->formatString( $this->order->get_billing_company() ),
					'indirizzo'      => $this->order->get_billing_address_2() ?
						$this->order->get_billing_address_1() . ' - ' . $this->order->get_billing_address_2() :
						$this->order->get_billing_address_1(),
					'localita'       => $this->formatString( $this->order->get_billing_city() ),
					'provincia'      => $this->formatString( $this->order->get_billing_state() ),
					'cap'            => $this->formatString( $this->order->get_billing_postcode() ),
					'telefono'       => $this->formatString( $this->order->get_billing_phone() ),
					'email'          => $this->formatString( $this->order->get_billing_email() ),
					'nazione'        => $this->formatString( $this->order->get_billing_country() ),
				),
				'importoTotale'      => $this->order->get_total(),
				'dataScadenza'       => $expiration_date,
				'causale'            => __( 'Payment of the order n. ', 'wp-pagopa-gateway-cineca' ) . $this->order->get_order_number(),
				'singoloVersamento'  => array(
					'codSingoloVersamentoEnte' => $this->order->get_order_number(),
					'importo'                  => $this->order->get_total(),
					'tributo'                  => array(
						'ibanAccredito'   => $this->plugin->settings['iban'],
						'tipoContabilita' => $this->plugin->settings['accounting_type'],
						'codContabilita'  => $this->plugin->settings['accounting_code'] . '/',
					),
				),
				'idModelloPagamento' => $this->plugin->settings['id_payment_model'],
			),
		);

		$result_code = 'KO';
		$esito       = '';
		$iuv         = '';
		try {
			$result = $this->soap_client->gpCaricaVersamento( $bodyrichiesta );
			if ( ! is_soap_fault( $result ) ) {
				if ( $result && ( 'OK' === $result->codEsitoOperazione ) ) {
					// Payment creation OK.
					$result_code = $result->codEsitoOperazione;
					$esito       = $result->codOperazione;
					$iuv         = $result->iuvGenerato->iuv;
					// error_log( '@@@ COD-OPERAZIONE' .  $esito); .
				} else {
					// Payment creation failed: Error in the Cineca response.
					$esito = $result ? $result->codOperazione . '-' . $result->descrizioneEsitoOperazione : 'Error in the Cineca response';
				}
			} else {
				// Payment creation failed: Error raised by the gateway.
				$esito = "SOAP Fault: (faultcode: {$result->faultcode}, faultstring: {$result->faultstring})";
			}
		} catch ( Exception $e ) {
			// Error creating a payment: Error contacting the gateway.
			$esito = $e->getMessage();
		}

		return array(
			'code' => $result_code,
			'iuv'  => $iuv,
			'msg'  => $esito,
		);
	}

	/**
	 * Checks if the string is valid, if not returns an empty.
	 *
	 * @param string $text - The text to be formatted.
	 * @return string
	 */
	private function formatString ( $text ) {
		if ( $text ) {
			return $text;
		} else {
			return '';
		}
	}

	/**
	 * Execute the SOAP call to check the status of the payment on the gateway.
	 *
	 * @return array
	 */
	public function get_payment_status() {
		$today         = gmdate( 'Y-m-d' );
		$bodyrichiesta = array(
			'codApplicazione'   => $this->plugin->settings['application_code'],
			'codVersamentoEnte' => $this->order->get_order_number(),
		);

		$result_code = 'KO';
		$esito       = '';
		$iuv         = '';
		try {
			$result = $this->soap_client->gpChiediStatoVersamento( $bodyrichiesta );
			if ( ! is_soap_fault( $result ) ) {
				if ( $result && ( 'OK' === $result->codEsitoOperazione ) ) {
					// Payment status retrieved.
					$result_code = $result->codEsitoOperazione;
					$esito       = $result->stato;
				} else {
					// Payment status not retrieved.
					$esito = $result ? $result->codOperazione : 'Error in the Cineca response';
				}
			} else {
				// Payment status retrieval failed: Error raised by the gateway.
				$esito = "SOAP Fault: (faultcode: {$result->faultcode}, faultstring: {$result->faultstring})";
			}
		} catch ( Exception $e ) {
			// Error retrieving the status of a payment: Error contacting the gateway.
			$esito = $e->getMessage();
		}

		return array(
			'code' => $result_code,
			'msg'  => $esito,
		);
	}

	/**
	 * Return the Gateway URL where the customer will pay the order.
	 *
	 * @param string $iuv - The IUV of the payment.
	 * @param string $hook - The fuction that is called from the gateway.
	 * @return string - The redirect url.
	 */
	public function get_payment_url( $iuv, $hook ) {
		$customer_code = $this->plugin->settings['application_code'];
		$order_code    = $this->order->get_order_number();
		$token         = $this->create_token( $order_code, $iuv );
		$order_hook    = trim( get_site_url(), '/' ) . '/wc-api/' . $hook . '?token=' . $token;
		$encoded_hook  = rawurlencode( $order_hook );
		$redirect_url  = $this->ws_data['frontend_base_url'] . PATH_FRONT_END_CINECA . '?cod_vers_ente=' . $order_code . '&cod_app=' . $customer_code . '&retUrl=' . $encoded_hook;
		return $redirect_url;
	}

	/**
	 * Creates the token to retrive order and Iuv.
	 *
	 * @param string $order_id - The id of the order.
	 * @param string $iuv - The Iuv of the payment.
	 * @return string - The token containing the session parameters.
	 */
	public static function create_token( $order_id, $iuv ) {
		$str_token = $order_id . PAR_SPLITTER . $iuv;
		return base64_encode( $str_token );
	}

	/**
	 * Decode the token to get the parameters.
	 *
	 * @param string $token - The token with the parameters.
	 * @return array - The array containing the parameters.
	 */
	public static function extract_token_parameters( $token ) {
		$decoded = base64_decode( $token );
		return explode( PAR_SPLITTER, $decoded );
	}



	/**
	 * Execute the SOAP call to get the status of a payment position on the gateway.
	 *
	 * @param string $iuv - Identificatore Universale Pagamento .
	 * @return void
	 */
	public function check_payment_status( $iuv ) {

	}

}
