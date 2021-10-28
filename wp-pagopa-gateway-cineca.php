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

/**
 *  This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'wp_gateway_pagopa_add' );
/**
 * Add PagoPa gateway.
 *
 * @param array $gateways .
 * @return array  $gateways .
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
			$this->testmode    = 'yes' === $this->get_option( 'testmode' );

			// This action hook saves the settings.
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// You can also register a webhook here.
		}

		/**
		 * Add the fields to the plugin configuration page.
		 *
		 * @return void
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'module_config_section' => array(
					'title' => __( 'Payment module configuration', 'wp-pagopa-gateway-cineca' ),
					'type' => 'title',
				),
				'enabled'              => array(
					'title'       => __( 'Enable/Disable', 'wp-pagopa-gateway-cineca' ),
					'label'       => __( 'Enable the gateway', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				// This controls the title which the user sees during checkout.
				'title'                 => array(
					'title'       => __( 'Title', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Title of PagoPa Gateway', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'PagoPA',
					'desc_tip'    => true,
				),
				// This controls the description which the user sees during checkout.
				'description'          => array(
					'title'       => __( 'Description', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Description of PagoPa Gateway', 'wp-pagopa-gateway-cineca' ),
					'default'     => __( 'Pay using the PagoPa gateway', 'wp-pagopa-gateway-cineca' ),
				),
				// Place the payment gateway in test mode using test API credentials.
				'testmode'             => array(
					'title'       => __( 'Enable test mode', 'wp-pagopa-gateway-cineca' ),
					'label'       => __( 'Enable test mode', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'checkbox',
					'description' => __( 'Place the payment gateway in test mode using test API credentials', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'application_code_prod' => array(
					'title'       => __( 'Application code', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The code of the application as defined in the Cineca backoffice', 'wp-pagopa-gateway-cineca' ),
				),
				'domain_code_prod'      => array(
					'title'       => __( 'Domain code', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'VAT code of the customer', 'wp-pagopa-gateway-cineca' ),
				),
				'iban_prod'             => array(
					'title'       => __( 'Iban', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Iban related to the e-commerce', 'wp-pagopa-gateway-cineca' ),
				),
				'accounting_type_prod'  => array(
					'title'       => __( 'Accounting type', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Acccounting type', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'ALTRO',
				),
				'accounting_code_prod'  => array(
					'title'       => __( 'Accounting code', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Accounting code as defined in the PagoPa taxonomy (https://www.pagopa.gov.it/it/pubbliche-amministrazioni/documentazione/#n3)', 'wp-pagopa-gateway-cineca' ),
					'default'     => '9/0601120SP/',
				),
				'id_payment_model'      => array(
					'title'       => __( 'Payment model ID', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'ID of payment model related to the e-commerce defined on the Cineca backoffice', 'wp-pagopa-gateway-cineca' ),
				),
				// Production credentials.
				'production_credentials'   => array(
					'title' => __( 'Production credentials', 'wp-pagopa-gateway-cineca' ),
					'type'  => 'title',
				),
				'base_url_prod'         => array(
					'title'       => __( 'API base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the API for the Cineca Payment Portal', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://gateway.pagoatenei.cineca.it',
				),
				'username_prod'         => array(
					'title'       => __( 'API username', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Username of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				'password_prod'         => array(
					'title'       => __( 'API password', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Password of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				// Test credentials.
				'test_credentials'         => array(
					'title' => __( 'Test credentials', 'wp-pagopa-gateway-cineca' ),
					'type'  => 'title',
				),
				'base_url_test'         => array(
					'title'       => __( 'API base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the API for the Cineca Payment Portal', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://gateway.pp.pagoatenei.cineca.it',
				),
				'username_test'         => array(
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
			// $order->update_status( 'on-hold', __( 'Payment in progress', 'wp_gateway_pagopa_init' ) ); .

			// BEGIN payment procedure.
			sleep( 5 );  // Wait 5 seconds to simulate the call to the web service.
			$payment_error = false;
			if ( $payment_error ) {
				// Payment OK.
				wc_add_notice( __( 'Payment error', 'wp_gateway_pagopa_init' ), 'error' );
				return;
			} else {
				// Payment KO.
				$order->payment_complete();
				// Notes for the customer.
				$order->add_order_note( __( 'The payment procedure was completed successfully', 'wp_gateway_pagopa_init' ) );
			}
			// END payment procedure.

			// Remove the cart.
			$woocommerce->cart->empty_cart();

			// Return the thankyou page.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

	}
}
