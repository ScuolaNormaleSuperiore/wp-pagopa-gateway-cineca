<?php
/**
 * PagoPA Gateway Cineca
 *
 * @package     PagoP Gateway Cineca
 * @author      ICT Scuola Normale Superiore
 * @copyright   © 2021-2022, SNS
 * @license     GNU General Public License v3.0
 *
 * Plugin Name: PagoPA Gateway Cineca
 * Plugin URI:
 * Description: Plugin to integrate WooCommerce with Cineca PagoPA payment portal
 * Version: 1.0.2-b1
 * Author: ICT Scuola Normale Superiore
 * Author URI: https://ict.sns.it
 * Text Domain: wp-pagopa-gateway-cineca
 * Domain Path: /lang
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Security check .
 */
if ( ! function_exists( 'add_action' ) ) {
	echo "Hi there, I'm just a plugin.";
	exit;
}

require_once 'inc/class-gateway-controller.php';
require_once 'inc/class-log-manager.php';

// Define some plugin constants.
define( 'HOOK_PAYMENT_COMPLETE', 'pagopa_payment_complete' );
define( 'DEBUG_MODE_ENABLED', 1 );
define( 'WAIT_NUM_SECONDS', 5 );
define( 'WAIT_NUM_ATTEMPTS', 6 );

// Register the hooks to install and uninstall the plugin.
register_activation_hook( __FILE__, 'install_pagopa_plugin' );
register_deactivation_hook( __FILE__, 'uninstall_pagopa_plugin' );

/**
 * Create or update the plugin log table
 *
 * @return void
 */
function install_pagopa_plugin() {
	// Create or update the log table.
	Log_Manager::init_table();
}

/**
 * Uninstall the plugin log table
 *
 * @return void
 */
function uninstall_pagopa_plugin() {

	// Removed the plugin tables.
	Log_Manager::drop_table();
}

/**
 *  This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'wp_gateway_pagopa_add' );
/**
 * Add PagoPA gateway.
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
 * Init PagoPA class.
 */
function wp_gateway_pagopa_init() {

	// Check if WooCommerce is installed.
	if ( is_admin() && ! class_exists( 'WC_Payment_Gateways' ) ) {
		echo '<div id="message" class="error"><p>ERROR: To use the plugin wp-pagopa-gateway-cineca WooCommerce must be installed!</p></div>';
		return;
	}
	// Check if the soap library is installed and enabled.
	if ( is_admin() && ! extension_loaded( 'soap' ) ) {
		echo '<div id="message" class="error"><p>ERROR: To use this plugin wp-pagopa-gateway-cineca the PHP Soap library must be installed and enabled!</p></div>';
		return;
	}

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
			$this->id           = 'pagopa_gateway_cineca';
			$this->icon         = plugins_url( 'assets/img/LogoPagoPaSmall2.png', __FILE__ );
			$this->has_fields   = true;
			$this->method_title = 'PagoPA Gateway';

			// The gateway supports simple payments.
			$this->supports = array(
				'products',
			);

			$this->load_plugin_textdomain();
			$this->method_description = __( 'Pay with the Cineca PagoPA Gateway', 'wp-pagopa-gateway-cineca' );

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
					'description' => __( 'The title of the plugin', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'PagoPA',
					'desc_tip'    => true,
				),
				// This controls the description which the user sees during checkout.
				'description'            => array(
					'title'       => __( 'Description', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The description of the plugin', 'wp-pagopa-gateway-cineca' ),
					'default'     => __( 'Pay using the Cineca Gateway for PagoPA', 'wp-pagopa-gateway-cineca' ),
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
					'description' => __( 'The code of the application as defined in the Cineca back office', 'wp-pagopa-gateway-cineca' ),
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
					'description' => __( 'Accounting type', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'ALTRO',
				),
				'accounting_code'        => array(
					'title'       => __( 'Accounting code', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Accounting code as defined in the PagoPA taxonomy (https://www.pagopa.gov.it/it/pubbliche-amministrazioni/documentazione/#n3)', 'wp-pagopa-gateway-cineca' ),
					'default'     => '0601120SP',
				),
				'id_payment_model'       => array(
					'title'       => __( 'Payment model ID', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'ID of payment model related to the e-commerce defined on the Cineca back office', 'wp-pagopa-gateway-cineca' ),
				),
				'cert_abs_path'          => array(
					'title'       => __( 'Certificate file name', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'File name of the .pem certificate', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'xx.it.pem',
				),
				'cert_passphrase'        => array(
					'title'       => __( 'Certificate passphrase', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Passphrase of the certificate', 'wp-pagopa-gateway-cineca' ),
				),
				// // Enable accounting fields.
				// 'accounting_fields_enabled'               => array(
				// 	'title'       => __( 'Enable accounting fields', 'wp-pagopa-gateway-cineca' ),
				// 	'label'       => __( 'Enable accounting fields', 'wp-pagopa-gateway-cineca' ),
				// 	'type'        => 'checkbox',
				// 	'description' => __( 'Enable during the checkout phase the fields: vat, fiscal code, sdi', 'wp-pagopa-gateway-cineca' ),
				// 	'default'     => 'false',
				// 	'desc_tip'    => true,
				// ),
				'order_prefix'          => array(
					'title'       => __( 'Order prefix', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The prefix that the plugin will prepend to the order number.It can be an empty string.', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'TEST',
				),
				// Production credentials.
				'production_credentials' => array(
					'title' => __( 'Production credentials', 'wp-pagopa-gateway-cineca' ),
					'type'  => 'title',
				),
				'base_fronted_url_prod'  => array(
					'title'       => __( 'Front end base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the Cineca front end', 'wp-pagopa-gateway-cineca' ),
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
					'title'       => __( 'Front end base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the Cineca front end', 'wp-pagopa-gateway-cineca' ),
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
			// Checkout fields should be validate earlier. That is in the checkout phase.
			// error_log( '@@@ validate fields @@@' );.
			return true;
		}

		/**
		 * Process the payment.
		 *
		 * @param int $order_id - 'The ID of the order'.
		 * @return array - 'Redirect page'.
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;

			// Retrieve the order details.
			$order       = new WC_Order( $order_id );
			$log_manager = new Log_Manager( $order );
			if ( DEBUG_MODE_ENABLED ) {
				error_log( '@@@ Process the order ewith the id: ' . $order_id );
			}

			// During the transaction the order is "on-hold".
			$log_manager->log( STATUS_PAYMENT_SUBMITTED );

			// Create the payment position on the Cineca gateway.
			$this->gateway_controller = new Gateway_Controller( $this );
			$this->gateway_controller->init( $order );
			$payment_position = $this->gateway_controller->load_payment_position();

			// Check if the payment postion was created successfully.
			if ( 'OK' !== $payment_position['code'] ) {
				// An error occurred creating the payment on the gateway.
				$error_msg  = __( 'Payment error reported by the gateway', 'wp-pagopa-gateway-cineca' );
				$error_desc = $error_msg . '-' . $payment_position['msg'];
				$log_manager->log( STATUS_PAYMENT_NOT_CREATED, null, $error_desc );
				wc_add_notice( $error_msg, 'error' );
				$redirect_url = wc_get_checkout_url();
				return array(
					'result'   => 'failed',
					'redirect' => $redirect_url,
				);
			}

			// Payment position created successfully.
			// Change the status of the order.
			$order->update_status( 'on-hold', __( 'Payment in progress', 'wp-pagopa-gateway-cineca' ) );
			// Payment saved.
			$log_manager->log( STATUS_PAYMENT_CREATED, $payment_position['iuv'] );
			// Redirect the customer to the gateway to pay the payment position just created.
			$redirect_url = $this->gateway_controller->get_payment_url( $payment_position['iuv'], HOOK_PAYMENT_COMPLETE );

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
			// error_log( '************ pagopa_payment_complete ************' );.

			$token      = ( ! empty( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '' );
			$id_session = ( ! empty( $_GET['idSession'] ) ? sanitize_text_field( wp_unslash( $_GET['idSession'] ) ) : '' );
			$outcome    = ( ! empty( $_GET['esito'] ) ? sanitize_text_field( wp_unslash( $_GET['esito'] ) ) : '' );
			$par_array  = Gateway_Controller::extract_token_parameters( $token );
			// Retrieve the parameters from the token.
			try {
				$order_id = $par_array[0];
				$iuv      = $par_array[1];
				if ( ( ! $order_id ) || ( ! $iuv ) ) {
					throw new Exception( 'Invalid token' );
				}
				// error_log( '@@@ ID SESSION: ' . $id_session . '@@@ ORDER ID: ' . $order_id . '@@@ IUV: ' . $iuv );.
			} catch ( Exception $e ) {
				// Error retrieving the parameters from the token.
				$error_msg = __( 'The gateway passed an invalid token for the order', 'wp-pagopa-gateway-cineca' );
				$error_msg = $error_msg . ' n. ' . $order_id;
				$this->error_redirect( $error_msg );
				return;
			}

			// Try to retrieve the order.
			try {
				$order = new WC_Order( $order_id );
			} catch ( Exception $e ) {
				// Error retrieving the order.
				$error_msg = __( 'Error retrieving the order', 'wp-pagopa-gateway-cineca' );
				$error_msg = $error_msg . 'n. ' . $order_id;
				$this->error_redirect( $error_msg );
				return;
			}

			$log_manager = new Log_Manager( $order );

			// Check the consinstence of the parameters: order and iuv.
			$p_status = $log_manager->get_current_status( $order_id, $iuv );
			if ( STATUS_PAYMENT_CREATED !== $p_status ) {
				// Error checking the parameters passed by the gateway.
				$error_msg = __( 'The status of the payment is not consistent', 'wp-pagopa-gateway-cineca' );
				$error_msg = $error_msg . ' n. ' . $order_id;
				$this->error_redirect( $error_msg );
				return;
			}

			// Check the outcome sent by the gateway.
			if ( ( 'OK' !== $outcome ) || ( '' === $iuv ) || ( '' === $order_id ) || ( '' === $id_session ) ) {
				// An error occurred during the payment phase or the payment has been cancelled by the customer.
				$error_msg = __( 'You have canceled the payment. To confirm the order, make the payment and contact the staff.', 'wp-pagopa-gateway-cineca' );
				if ( $order_id ) {
					$error_msg = $error_msg . ' - ' . __( 'Order id', 'wp-pagopa-gateway-cineca' ) . ': ' . $order_id;
				}
				if ( $iuv ) {
					$error_msg = $error_msg . ' - ' . __( 'Iuv', 'wp-pagopa-gateway-cineca' ) . ': ' . $iuv;
				}
				$log_manager->log( STATUS_PAYMENT_NOT_CREATED, null, $error_msg );
				$this->error_redirect( $error_msg );
				return;
			}

			// Payment executed.
			$log_manager->log( STATUS_PAYMENT_EXECUTED, $iuv );

			// Ask the status of the payment to the gateway.
			$this->gateway_controller = new Gateway_Controller( $this );
			$this->gateway_controller->init( $order );

			$executed     = false;
			$num_attempts = 1;
			for ( $num_attempts; $executed === false && $num_attempts <= WAIT_NUM_ATTEMPTS; $num_attempts++ ) {
				$payment_status = $this->gateway_controller->get_payment_status();
				if ( $payment_status && ( 'OK' === $payment_status['code'] ) && ( 'ESEGUITO' === $payment_status['msg'] ) ) {
					// Payment executed, exit from the loop.
					$executed = true;
					break;
				} elseif ( $payment_status && ( 'OK' === $payment_status['code'] ) && ( 'NON_ESEGUITO' === $payment_status['msg'] ) ) {
					// Payment not yet executed wait and retry.
					sleep( WAIT_NUM_SECONDS );
					$executed = false;
				} else {
					// Error reported by the gateway, exit from the loop.
					$executed = false;
					break;
				}
			}

			$num_attempts = $num_attempts <= WAIT_NUM_ATTEMPTS ? $num_attempts : $num_attempts - 1;

			if ( ! ( $executed ) ) {
				// Payment not confirmed.
				$error_msg  = __( 'Payment not confirmed by the gateway. Please contact the staff, the order number is:', 'wp-pagopa-gateway-cineca' );
				$error_msg  = $error_msg . ' ' . $order_id . ' - Iuv: ' . $iuv;
				// ATT!! Only for debug purposes (remove the following line of code asap).
				$error_desc = $error_msg . ' - Code:' . $payment_status['code'];
				$error_desc = $error_desc . ' - Status: ' . $payment_status['msg'];
				$error_desc = $error_desc . ' - Attempts: ' . $num_attempts;
				$log_manager->log( STATUS_PAYMENT_NOT_CONFIRMED, $iuv, $error_desc );
				$this->error_redirect( $error_msg );
				return;
			} else {
				// Payment confirmed.
				$order->payment_complete();
				$log_desc = 'Attemps: ' . $num_attempts;
				$log_manager->log( STATUS_PAYMENT_CONFIRMED, $iuv, $log_desc );
				$redirect_url = $this->get_return_url( $order );
				wp_safe_redirect( $redirect_url );
			}

		}

		/**
		 * Redirect on the checkout page when an error occurs.
		 *
		 * @param string $error_msg - The error message shown to the customer.
		 * @param string $error_description - The error description.
		 * @return void
		 */
		private function error_redirect( $error_msg, $error_description='' ) {
			wc_add_notice( $error_msg, 'error' );
			$redirect_url = wc_get_checkout_url();
			wp_safe_redirect( $redirect_url );
		}

	} // end class
}
