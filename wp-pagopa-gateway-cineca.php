<?php

/**
 * PagoPa Gateway.
 *
 * @package     wp-pagopa-gateway-cineca.php
 * @author      ICT Scuola Normale Superiore
 * @copyright   © 2021-2022, SNS
 * @license     GNU General Public License v3.0
 *
 * Plugin Name: PagoPa Gateway
 * Plugin URI:
 * Description: Plugin to integrate WooCommerce with Cineca PagoPa payment portal
 * Version: 0.0.1
 * Author: ICT Scuola Normale Superiore
 * Author URI: https://ict.sns.it
 * Text Domain: wp-pagopa-gateway-cineca
 * Domain Path: /lang
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

require_once 'inc/class-gateway-controller.php';

define( 'HOOK_PAYMENT_COMPLETE', 'pagopa_payment_complete' );

/**
 *  This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'wp_gateway_pagopa_add' );
/**
 * Add PagoPa gateway.
 *
 * @param  array $gateways .
 * @return array $gateways .
 */
function wp_gateway_pagopa_add( $gateways ) {
	$gateways[] = 'WP_Gateway_PagoPa';
	return $gateways;
}

add_action( 'plugins_loaded', 'wp_gateway_pagopa_init' );
/**
 * Init PagoPa class.
 */
function wp_gateway_pagopa_init() {

	/**
	 * Add the gateway(s) to WooCommerce.
	 */
	class WP_Gateway_PagoPa extends WC_Payment_Gateway {

		/**
		 * Load the translations.
		 *
		 * @return void
		 */
		private function load_plugin_textdomain() {
			load_plugin_textdomain( 'wp-pagopa-gateway-cineca', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		}

		/**
		 * Define the fields of the plugin.
		 */
		public function __construct() {
			$this->id                 = 'pagopa_gateway_cineca';
			$this->icon               = '';
			$this->has_fields         = true;
			$this->method_title       = 'PagoPA Gateway';
			$this->method_description = 'Pay using the Cineca PagoPa Gateway';
			error_log( '@@@ CONSTRUCT @@@' );

			// The gateway supports simple payments.
			$this->supports = array(
				'products',
			);

			$this->load_plugin_textdomain();

			// Method with all the options fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Load the settings into variables.
			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled     = $this->get_option( 'enabled' );
			$this->testmode    = $this->get_option( 'testmode' );

			// This action hook saves the settings.
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// You can also register a webhook here.
			add_action( 'woocommerce_api_' . HOOK_PAYMENT_COMPLETE, array( $this, 'webhook_payment_complete' ) );
			error_log( '@@@ DEFINE HOOK @@@' );
		}

		/**
		 * Add the fields to the plugin configuration page.
		 *
		 * @return void
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'module_config_section'  => array(
					'title' => __( 'Payment module configuration', 'wp-pagopa-gateway-cineca' ),
					'type'  => 'title',
				),
				'enabled'                => array(
					'title'       => __( 'Enable/Disable', 'wp-pagopa-gateway-cineca' ),
					'label'       => __( 'Enable the gateway', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				// This controls the title which the user sees during checkout.
				'title'                  => array(
					'title'       => __( 'Title', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Title of PagoPa Gateway', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'PagoPA',
					'desc_tip'    => true,
				),
				// This controls the description which the user sees during checkout.
				'description'            => array(
					'title'       => __( 'Description', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Description of PagoPa Gateway', 'wp-pagopa-gateway-cineca' ),
					'default'     => __( 'Pay using the PagoPa gateway', 'wp-pagopa-gateway-cineca' ),
				),
				// Place the payment gateway in test mode using test API credentials.
				'testmode'               => array(
					'title'       => __( 'Enable test mode', 'wp-pagopa-gateway-cineca' ),
					'label'       => __( 'Enable test mode', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'checkbox',
					'description' => __( 'Place the payment gateway in test mode using test API credentials', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'application_code'       => array(
					'title'       => __( 'Application code', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The code of the application as defined in the Cineca backoffice', 'wp-pagopa-gateway-cineca' ),
				),
				'domain_code'            => array(
					'title'       => __( 'Domain code', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'VAT code of the customer', 'wp-pagopa-gateway-cineca' ),
				),
				'iban'                   => array(
					'title'       => __( 'Iban', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Iban related to the e-commerce', 'wp-pagopa-gateway-cineca' ),
				),
				'accounting_type'        => array(
					'title'       => __( 'Accounting type', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Acccounting type', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'ALTRO',
				),
				'accounting_code'        => array(
					'title'       => __( 'Accounting code', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Accounting code as defined in the PagoPa taxonomy (https://www.pagopa.gov.it/it/pubbliche-amministrazioni/documentazione/#n3)', 'wp-pagopa-gateway-cineca' ),
					'default'     => '0601120SP',
				),
				'id_payment_model'       => array(
					'title'       => __( 'Payment model ID', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'ID of payment model related to the e-commerce defined on the Cineca backoffice', 'wp-pagopa-gateway-cineca' ),
				),
				'cert_abs_path'          => array(
					'title'       => __( 'Certificate path', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Absolute path of the .pem certificate', 'wp-pagopa-gateway-cineca' ),
					'default'     => '/var/www/ecommerce/wp-content/plugins/wp-pagopa-gateway-cineca/mycert.pem',
				),
				'cert_passphrase'        => array(
					'title'       => __( 'Certificate passphrase', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Passphrase of the certificate', 'wp-pagopa-gateway-cineca' ),
				),
				// Production credentials.
				'production_credentials' => array(
					'title' => __( 'Production credentials', 'wp-pagopa-gateway-cineca' ),
					'type'  => 'title',
				),
				'base_fronted_url_prod'  => array(
					'title'       => __( 'Front-end base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the Cineca Front-end', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://xxx.pagoatenei.cineca.it',
				),
				'base_url_prod'          => array(
					'title'       => __( 'API base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the API for the Cineca Payment Portal', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://gateway.pagoatenei.cineca.it',
				),
				'username_prod'          => array(
					'title'       => __( 'API username', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Username of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				'password_prod'          => array(
					'title'       => __( 'API password', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Password of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				// Test credentials.
				'test_credentials'       => array(
					'title' => __( 'Test credentials', 'wp-pagopa-gateway-cineca' ),
					'type'  => 'title',
				),
				'base_fronted_url_test'  => array(
					'title'       => __( 'Front-end base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the Cineca Front-end', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://xxx.pagoatenei.cineca.it',
				),
				'base_url_test'          => array(
					'title'       => __( 'API base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the API for the Cineca Payment Portal', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://gateway.pp.pagoatenei.cineca.it',
				),
				'username_test'          => array(
					'title'       => __( 'API username', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Username of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				'password_test'          => array(
					'title'       => __( 'API password', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Password of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
			);

		}

		/**
		 * Validate the fields of the payment form.
		 *
		 * @return boolean
		 */
		public function validate_fields() {
			/* Checkout fields should be validate earlier. That is in the checkout phase. */
			error_log( '@@@ validate fields @@@' );
			return true;
		}

		/**
		 * Process the payment.
		 *
		 * @param int $order_id 'The ID of the order'.
		 * @return array 'Redirect page'.
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;

			// Retrieve the order details.
			$order = new WC_Order( $order_id );

			// During the transaction the order is "on-hold".
			$order->update_status( 'on-hold', __( 'Payment in progress', 'wp_gateway_pagopa_init' ) );

			// Create the payment position on the Cineca gateway.
			$this->gateway_controller = new Gateway_Controller( $this );
			$this->gateway_controller->init( $order );
			$payment_position = $this->gateway_controller->load_payment_position();

			// Check if the payment postion was created successfully.
			if ( 'OK' !== $payment_position['code'] ) {
				wc_add_notice( __( 'Payment error', 'wp_gateway_pagopa_init' ) . '-' . $payment_position['msg'], 'error' );
				return;
			}

			// Redirect the customer to the gateway to pay the payment position just created.
			$redirect_url = $this->gateway_controller->get_payment_url( $payment_position['iuv'], HOOK_PAYMENT_COMPLETE );
			error_log( '### REDIRECT URL:' . $redirect_url );

			return array(
				'result'   => 'success',
				'redirect' => $redirect_url,
			);
		}

		/**
		 * Hook called by the Gateway after the customer has paid.
		 *
		 * @param array $args - Arguments of the function.
		 * @return void - Redirect to the thankyou page.
		 */
		public function webhook_payment_complete( $args ) {
			error_log( '************ pagopa_payment_complete ************' );

			$order_id   = ( ! empty( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '' );
			$iuv        = ( ! empty( $_GET['iuv'] ) ? sanitize_text_field( wp_unslash( $_GET['iuv'] ) ) : '' );
			$id_session = ( ! empty( $_GET['idSession'] ) ? sanitize_text_field( wp_unslash( $_GET['idSession'] ) ) : '' );
			$outcome    = ( ! empty( $_GET['esito'] ) ? sanitize_text_field( wp_unslash( $_GET['esito'] ) ) : '' );

			if ( ( 'OK' !== $outcome ) || ( '' === $iuv ) || ( '' === $order_id ) || ( '' === $id_session ) ) {
				// An error occurred during the payment phase or the payment has been cancelled by the customer.
				wc_add_notice( __( 'Payment cancelled', 'wp_gateway_pagopa_init' ), 'error' );
				return;
			}

			// @TODO: Try except sull'ordine.
			$order = wc_get_order( $order_id );

			// @TODO: Verifica dello stato dell'ordine.

			/ /@TODO: gpChiediStatoVersamento .

			$order->payment_complete();

			$redirect_url = $this->get_return_url( $order );
			wp_safe_redirect( $redirect_url );
		}
	}
}
