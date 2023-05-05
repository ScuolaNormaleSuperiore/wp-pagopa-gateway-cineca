<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.1.4
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

define( 'PATH_WSDL_CINECA', '/portalepagamenti.server.gateway/api/private/soap/GPAppPort?wsdl' );
define( 'PATH_FRONT_END_CINECA', '/portalepagamenti.server.frontend/#/ext' );
define( 'PAR_SPLITTER', '||' );
define( 'USER_AGENT', 'Wordpress/PagoPaGatewayCineca' );

/**
 * Gateway_Controller class
 */
class Gateway_Controller {

	/**
	 * Create the Gateway controller.
	 */
	public function __construct() {
		$this->options = self::get_plugin_options();
		$this->ws_data = array();

		if ( 'yes' === $this->options['testmode'] ) {
			// Get the parameters of the TEST configutation .
			$this->ws_data['frontend_base_url']   = trim( $this->options['base_fronted_url_test'], '/' );
			$this->ws_data['ws_soap_base_url']    = trim( $this->options['base_url_test'], '/' );
			$this->ws_data['ws_username']         = $this->options['username_test'];
			$this->ws_data['ws_password']         = $this->options['password_test'];
			$this->ws_data['id_payment_model']    = $this->options['id_payment_model_test'];
			$this->ws_data['confirm_payment']     = $this->options['confirm_payment'];
			$this->ws_data['payment_conf_method'] = $this->options['payment_conf_method'];
		} else {
			// Get the parameters of the PRODUCTION configutation .
			$this->ws_data['frontend_base_url']   = trim( $this->options['base_fronted_url_prod'], '/' );
			$this->ws_data['ws_soap_base_url']    = trim( $this->options['base_url_prod'], '/' );
			$this->ws_data['ws_username']         = $this->options['username_prod'];
			$this->ws_data['ws_password']         = $this->options['password_prod'];
			$this->ws_data['id_payment_model']    = $this->options['id_payment_model_prod'];
			$this->ws_data['confirm_payment']     = $this->options['confirm_payment'];
			$this->ws_data['payment_conf_method'] = $this->options['payment_conf_method'];
		}
	}

	/**
	 * Init the controller
	 *
	 * @param WC_Order $order - The e-commerce order.
	 * @return array - The result of the initialization (code and msg).
	 */
	public function init( $order ) {
		$this->order      = $order;
		$this->local_cert = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wp-pagopa-gateway-cineca' . DIRECTORY_SEPARATOR . 'cert' . DIRECTORY_SEPARATOR . $this->options['cert_abs_path'];
		$this->local_cert = wp_normalize_path( $this->local_cert );
		$this->passphrase = $this->options['cert_passphrase'];

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

		$this->wsdl_url      = $this->ws_data['ws_soap_base_url'] . PATH_WSDL_CINECA;
		$soap_client_options = array(
			'user_agent'         => USER_AGENT,
			'login'              => $this->ws_data['ws_username'],
			'password'           => $this->ws_data['ws_password'],
			'authentication'     => SOAP_AUTHENTICATION_BASIC,
			'exception'          => true,
			'keep_alive '        => false,
			'encoding'           => 'UTF-8',
			'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
			'cache_wsdl'         => WSDL_CACHE_NONE,
			'trace'              => true,
			'connection_timeout' => intval( TOTAL_SOAP_TIMEOUT ),
			'local_cert'         => $this->local_cert,
			'passphrase'         => $this->passphrase,
			'stream_context'     => stream_context_create( $context_options ),
			'soap_version'       => SOAP_1_1,
		);

		$init_result = array(
			'code' => '',
			'msg'  => '',
		);

		try {
			$init_result['code'] = 'OK';
			$init_result['msg']  = 'created';
			$this->soap_client   = new SoapClient(
				$this->wsdl_url,
				$soap_client_options,
			);
		} catch ( Exception $e ) {
			// Error creating the Soap connection.
			$init_result['code'] = 'KO';
			$init_result['msg']  = $e->getMessage();
		}
		return $init_result;
	}


	/**
	 * Execute the SOAP call to load payment position on the gateway.
	 *
	 * @return array
	 */
	public function load_payment_position() {
		$hours_toexpire  = intval( $this->options['expire_hours'] );
		$expiration_date = gmdate( 'Y-m-d\TH:i:s', strtotime( $hours_toexpire . ' hour' ) );

		// If the VAT field is specified then the customer is a company not a person.
		$vat              = $this->order->get_meta( '_billing_vat' );
		$billing_coompany = $this->order->get_billing_company();
		if ( $billing_coompany && $vat ) {
			// The customer is a company.
			$codice_univoco  = $this->format_string( $vat );
			$ragione_sociale = $this->format_string( $this->order->get_billing_company() );
		} else {
			// The customer is a person.
			$codice_univoco  = $this->format_string( $this->order->get_meta( '_billing_ita_cf' ) );
			$ragione_sociale = $this->format_string( $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name() );
		}

		// If neither vat nor piva is specified.
		if ( '' === trim( $codice_univoco ) ) {
			$codice_univoco = $ragione_sociale;
		}

		$raw_order_number = self::build_raw_order_number( $this->options['order_prefix'], $this->order->get_order_number() );

		$bodyrichiesta = array(
			'generaIuv'        => true,
			'aggiornaSeEsiste' => true,
			'versamento'       => array(
				'codApplicazione'    => $this->options['application_code'],
				'codVersamentoEnte'  => $raw_order_number,
				'codDominio'         => $this->options['domain_code'],
				'debitore'           => array(
					'codUnivoco'     => $codice_univoco,
					'indirizzo'      => $this->order->get_billing_address_2() ?
								$this->order->get_billing_address_1() . ' - ' . $this->order->get_billing_address_2() :
								$this->order->get_billing_address_1(),
					'ragioneSociale' => $ragione_sociale,
					'localita'       => $this->format_string( $this->order->get_billing_city() ),
					'provincia'      => $this->format_string( $this->order->get_billing_state() ),
					'cap'            => $this->format_string( $this->order->get_billing_postcode() ),
					'telefono'       => $this->format_string( $this->order->get_billing_phone() ),
					'cellulare'      => $this->format_string( $this->order->get_billing_phone() ),
					'email'          => $this->format_string( $this->order->get_billing_email() ),
					'nazione'        => $this->format_string( $this->order->get_billing_country() ),
				),
				'importoTotale'      => $this->order->get_total(),
				'dataScadenza'       => $expiration_date,
				'causale'            => __( 'Payment of the order n.', 'wp-pagopa-gateway-cineca' ) . ' ' . $this->order->get_order_number(),
				'singoloVersamento'  => array(
					'codSingoloVersamentoEnte' => $this->order->get_order_number(),
					'importo'                  => $this->order->get_total(),
					'tributo'                  => array(
						'ibanAccredito'   => $this->options['iban'],
						'tipoContabilita' => $this->options['accounting_type'],
						'codContabilita'  => $this->options['accounting_code'] . '/',
					),
				),
				'idModelloPagamento' => $this->ws_data['id_payment_model'],
			),
		);

		if ( DEBUG_MODE_ENABLED ) {
			error_log( print_r( $bodyrichiesta, true ) );
		}

		$result_code = '';
		$esito       = '';
		$iuv         = '';
		try {
			$result = $this->soap_client->gpCaricaVersamento( $bodyrichiesta );
			if ( ! is_soap_fault( $result ) ) {
				if ( $result && ( 'OK' === $result->codEsitoOperazione ) ) {
					// Payment creation OK.
					$result_code = 'OK';
					$esito       = $result->codOperazione;
					$iuv         = $result->iuvGenerato->iuv;
				} else {
					// Payment creation failed: Error in the Cineca response.
					$esito       = $this->get_error_message( $result );
					$result_code = 'KO';
				}
			} else {
				// Payment creation failed: Error raised by the gateway.
				$esito       = "SOAP Fault: (faultcode: {$result->faultcode}, faultstring: {$result->faultstring})";
				$result_code = 'KO';
			}
		} catch ( Exception $e ) {
			// Error creating a payment: Error contacting the gateway.
			$esito       = $e->getMessage();
			$result_code = 'KO';
		}

		if ( DEBUG_MODE_ENABLED ) {
			error_log( print_r( $result, true ) );
		}

		return array(
			'code' => $result_code,
			'iuv'  => $iuv,
			'msg'  => $esito,
		);
	}

	/**
	 * Build the order number given prefix and order number.
	 *
	 * @param string $order_prefix - The prefix that must be added to the order.
	 * @param string $order_number - The number of the order.
	 * @return string
	 */
	public static function build_raw_order_number( $order_prefix, $order_number ) {
		// Add the char "-" to the prefix, if present.
		return trim( $order_prefix ) ? trim( $order_prefix ) . '-' . $order_number : $order_number;
	}

	/**
	 * Extract the order number without order prefix.
	 *
	 * @param string $order_prefix - The prefix of the order.
	 * @param string $raw_order_number - The raw order number.
	 * @return string
	 */
	public static function extract_order_number( $order_prefix, $raw_order_number ) {
		if ( trim( $order_prefix ) ) {
			return trim( str_replace( $order_prefix . '-', '', $raw_order_number ) );
		}
		return $raw_order_number;
	}


	/**
	 * Checks if the string is valid, if not returns an empty.
	 *
	 * @param string $text - The text to be formatted.
	 * @return string
	 */
	private function format_string( $text ) {
		if ( $text ) {
			return $text;
		} else {
			return ' ';
		}
	}

	/**
	 * Execute the SOAP call to check the status of the payment on the gateway.
	 *
	 * @return array
	 */
	public function get_payment_status() {
		$raw_order_number = self::build_raw_order_number( $this->options['order_prefix'], $this->order->get_order_number() );
		$bodyrichiesta    = array(
			'codApplicazione'   => $this->options['application_code'],
			'codVersamentoEnte' => $raw_order_number,
		);

		if ( DEBUG_MODE_ENABLED ) {
			error_log( print_r( $bodyrichiesta, true ) );
		}

		$result_code = '';
		$esito       = '';
		try {
			$result = $this->soap_client->gpChiediStatoVersamento( $bodyrichiesta );
			if ( ! is_soap_fault( $result ) ) {
				if ( $result && ( 'OK' === $result->codEsitoOperazione ) ) {
					// Payment status retrieved.
					$result_code = 'OK';
					$esito       = $result->stato;
				} else {
					// Payment status not retrieved.
					$esito       = $this->get_error_message( $result );
					$result_code = 'KO';
				}
			} else {
				// Payment status retrieval failed: Error raised by the gateway.
				$esito       = "SOAP Fault: (faultcode: {$result->faultcode}, faultstring: {$result->faultstring})";
				$result_code = 'KO';
			}
		} catch ( Exception $e ) {
			// Error retrieving the status of a payment: Error contacting the gateway.
			$esito       = $e->getMessage();
			$result_code = 'KO';
		}

		if ( DEBUG_MODE_ENABLED ) {
			error_log( print_r( $result, true ) );
		}

		return array(
			'code' => $result_code,
			'msg'  => $esito,
		);
	}

	/**
	 * Build the message of the error.
	 *
	 * @param object $result - The result of the soap call.
	 * @return string
	 */
	private function get_error_message( $result ) {
		$message_text = __( 'Error in the Cineca response', 'wp-pagopa-gateway-cineca' );
		if ( $result->codOperazione ) {
			$message_text = $message_text . ' - CodOperazione: ' . $result->codOperazione;
		}
		if ( $result->codEsitoOperazione ) {
			$message_text = $message_text . ' - CodEsitoOperazione: ' . $result->codEsitoOperazione;
		}
		if ( $result->descrizioneEsitoOperazione ) {
			$message_text = $message_text . ' - DescrizioneEsitoOperazione: ' . $result->descrizioneEsitoOperazione;
		}
		return $message_text;
	}

	/**
	 * Return the Gateway URL where the customer will pay the order.
	 *
	 * @param string $iuv - The IUV of the payment.
	 * @param string $hook - The fuction that is called from the gateway.
	 * @return string - The redirect url.
	 */
	public function get_payment_url( $iuv, $hook ) {
		$customer_code    = $this->options['application_code'];
		$order_number     = $this->order->get_order_number();
		$raw_order_number = self::build_raw_order_number( $this->options['order_prefix'], $order_number );
		$token            = self::create_token( $order_number, $iuv );
		$order_hook       = trim( get_site_url(), '/' ) . '/wc-api/' . $hook . '?token=' . $token;
		$encoded_hook     = rawurlencode( $order_hook );
		$redirect_url     = $this->ws_data['frontend_base_url'] . PATH_FRONT_END_CINECA . '?cod_vers_ente=' . $raw_order_number . '&cod_app=' . $customer_code . '&retUrl=' . $encoded_hook;
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
		$plain_token     = $order_id . PAR_SPLITTER . $iuv;
		$options         = self::get_plugin_options();
		$key             = $options['encryption_key'];
		$encrypted_token = Encryption_Manager::encrypt_text( $plain_token, $key );
		return base64_encode( $encrypted_token );
	}

	/**
	 * Decode the token to get the parameters.
	 *
	 * @param string $token - The token with the parameters.
	 * @return array - The array containing the parameters.
	 */
	public static function extract_token_parameters( $token ) {
		$options = self::get_plugin_options();
		$key     = $options['encryption_key'];
		$decoded = Encryption_Manager::decrypt_text( base64_decode( $token ), $key );
		return explode( PAR_SPLITTER, $decoded );
	}

	/**
	 * Return the plugin options.
	 *
	 * @return array -  The options of the plugin.
	 */
	private static function get_plugin_options() {
		return get_option( 'woocommerce_pagopa_gateway_cineca_settings' );
	}

}
