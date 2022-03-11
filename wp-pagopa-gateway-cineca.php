<?php
/**
 * PagoPA Gateway Cineca
 *
 * @package     PagoPA Gateway Cineca
 * @author      ICT Scuola Normale Superiore
 * @copyright   © 2021-2022, SNS
 * @license     GNU General Public License v3.0
 *
 * Plugin Name: PagoPA Gateway Cineca
 * Plugin URI:
 * Description: Plugin to integrate WooCommerce with Cineca PagoPA payment portal
 * Version: 1.0.18
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
require_once 'inc/class-encryption-manager.php';
require_once 'inc/class-transactions-manager.php';

// Define some plugin constants.
define( 'HOOK_PAYMENT_COMPLETE', 'pagopa_payment_complete' );
define( 'HOOK_SCHEDULED_ACTIONS', 'pagopa_execute_actions' );
define( 'HOOK_TRANSACTION_NOTIFICATION', 'pagopa_notifica_transazione' );
define( 'DEBUG_MODE_ENABLED', 1 );
define( 'WAIT_NUM_SECONDS', 5 );
define( 'WAIT_NUM_ATTEMPTS', 35 );
define( 'NUM_DAYS_TO_CHECK', 7 );
define( 'HTML_EMAIL_HEADERS', array( 'Content-Type: text/html; charset=UTF-8' ) );

define( 'TOTAL_SOAP_TIMEOUT', 20 );

define( 'NOTIFY_TRANSACTION_RESPONSE',
	'<?xml version=\'1.0\' encoding=\'UTF-8\'?>' .
	'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">' .
	'<soapenv:Body>' .
	'<ns2:paNotificaTransazioneResponse xmlns:ns2="http://www.govpay.it/servizi/pa/">' .
	'OK' .
	'</ns2:paNotificaTransazioneResponse>' .
	'</soapenv:Body>' .
	'</soapenv:Envelope>'
);


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

	// If WooCommerce is installed add a page in its menu.
	if ( class_exists( 'woocommerce' ) ) {
		add_action( 'admin_menu', 'add_plugin_menu' );
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
			$this->icon         = plugins_url( 'assets/img/LogoPagoPaSmall.png', __FILE__ );
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
			$desc = '';
			if ( 'it_IT' === get_locale() ) {
				$desc = $this->get_option( 'description' );
			} else {
				$desc = $this->get_option( 'description_en' );
			}

			$icon_list         = $this->get_icon_list();
			$this->title       = $this->get_option( 'title' );
			$this->description = $icon_list . $desc;
			$this->enabled     = $this->get_option( 'enabled' );
			$this->testmode    = $this->get_option( 'testmode' );
			$this->api_user    = ( 'yes' === $this->testmode ) ?
				trim( $this->get_option( 'api_user_test' ) ) :
				trim( $this->get_option( 'api_user_prod' ) );
			$this->api_pwd     = ( 'yes' === $this->testmode ) ?
				trim( $this->get_option( 'api_pwd_test' ) ) :
				trim( $this->get_option( 'api_pwd_prod' ) );

			// This action hook saves the settings.
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Register the webhook to handle the gateway response.
			add_action( 'woocommerce_api_' . HOOK_PAYMENT_COMPLETE, array( $this, 'webhook_payment_complete' ) );

			// Register the webhook to start scheduled jobs.
			add_action( 'woocommerce_api_' . HOOK_SCHEDULED_ACTIONS, array( $this, 'webhook_scheduled_actions' ) );

			// Register the webhook called from the gateway to notify a payment.
			add_action( 'woocommerce_api_' . HOOK_TRANSACTION_NOTIFICATION, array( $this, 'webhook_transaction_notification' ) );

			// Use a custom style.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		}

		/**
		 * Return a string with all the icons of the available payment methods.
		 *
		 * @return string
		 */
		private function get_icon_list() {

			$img_list = '';
			$folder   = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wp-pagopa-gateway-cineca' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'cc';
			$folder   = wp_normalize_path( $folder );
			$files    = glob( $folder . '/*' );

			if ( is_array( $files ) ) {
				foreach ( $files as $fp ) {
					$filename  = basename( $fp );
					$file_url  = self::get_plugin_url() . '/assets/img/cc/' . $filename;
					$img_list .= $this->getImgSrc( $file_url );
				}
			}

			if ( '' !== $img_list ) {
				$img_list = "<span id='ppa_icon_list'>" . $img_list . '</span><br>';
			}
			return $img_list;
		}

		/**
		 *
		 * Return the code of each icon to display.
		 *
		 * @param string $link - The absolute url of the image.
		 * @return string - The link of the image.
		 */
		private function getImgSrc( $link ) {
			return "<span class='ppa_card_icon'><img class='ppa_card_icon_img' src='" . $link . "' ></span>";
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
				// This controls the description that the user sees during checkout.
				'description_en'         => array(
					'title'       => __( 'Default description', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The description of the plugin shown in the checkout page for non italian users.', 'wp-pagopa-gateway-cineca' ),
					'default'     => __( 'Pay using the Cineca Gateway for PagoPA', 'wp-pagopa-gateway-cineca' ),
				),
				'description'            => array(
					'title'       => __( 'Description', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The italian description of the plugin shown in the checkout page.', 'wp-pagopa-gateway-cineca' ),
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
				'payment_conf_method'    => array(
					'title'       => __( 'Payment confirmation method', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'select',
					'default'     => 'SYNC-INT',
					'options'     => $this->get_options_conf_method(),
					'desc_tip'    => __( 'The confirmation of a payment can be synchronous (polling on PagoAtenei waiting for its status to be updated by the PSP) or asynchronous (the order is confirmed by PagoAtenei\'s paNotificaTransazione notification).', 'wp-pagopa-gateway-cineca' ),
				),
				'confirm_payment'        => array(
					'title'       => __( 'Payment confirmation', 'wp-pagopa-gateway-cineca' ),
					'label'       => __( 'Ask for payment confirmation', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'checkbox',
					'description' => __( 'Checks the status of the payment in the callback procedure', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'no',
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
					'description' => __( 'Accounting code as defined in the PagoPA taxonomy (https://github.com/pagopa/pagopa-api/blob/develop/taxonomy/tassonomia.json)', 'wp-pagopa-gateway-cineca' ),
					'default'     => '0601120SP',
				),
				'expire_hours'           => array(
					'title'       => __( 'Validity of payment', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'number',
					'description' => __( 'Validity of payment in hours', 'wp-pagopa-gateway-cineca' ),
					'default'     => '24',
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
				'order_prefix'           => array(
					'title'       => __( 'Order prefix', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The prefix that the plugin will prepend to the order number.It can be an empty string.', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'TEST',
				),
				'encryption_key'         => array(
					'title'       => __( 'Encryption key', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'The key used to encrypt the token.', 'wp-pagopa-gateway-cineca' ),
				),
				'api_token'              => array(
					'title'       => __( 'API token', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Token used by the REST API and the scheduled actions.', 'wp-pagopa-gateway-cineca' ),
				),
				// Production credentials.
				'production_credentials'  => array(
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
					'title'       => __( 'PagoAtenei API base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the API for the Cineca Payment Portal', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://gateway.pagoatenei.cineca.it',
				),
				'username_prod'          => array(
					'title'       => __( 'PagoAtenei API username', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Username of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				'password_prod'          => array(
					'title'       => __( 'PagoAtenei API password', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Password of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				'api_user_prod'          => array(
					'title'       => __( 'Local API username', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Username of the account enabled to the use of the plugin\'s api', 'wp-pagopa-gateway-cineca' ),
				),
				'api_pwd_prod'           => array(
					'title'       => __( 'Local API password', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Password of the account enabled to the use of the plugin\'s api', 'wp-pagopa-gateway-cineca' ),
				),
				'id_payment_model_prod'  => array(
					'title'       => __( 'Payment model ID', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'ID of payment model related to the e-commerce defined on the Cineca back office', 'wp-pagopa-gateway-cineca' ),
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
					'title'       => __( 'PagoAtenei API base url', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Base url of the API for the Cineca Payment Portal', 'wp-pagopa-gateway-cineca' ),
					'default'     => 'https://gateway.pp.pagoatenei.cineca.it',
				),
				'username_test'          => array(
					'title'       => __( 'PagoAtenei API username', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Username of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				'password_test'          => array(
					'title'       => __( 'PagoAtenei API password', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Password of the account enabled to the use of the API', 'wp-pagopa-gateway-cineca' ),
				),
				'api_user_test'          => array(
					'title'       => __( 'Local API username', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Username of the account enabled to the use of the plugin\'s api', 'wp-pagopa-gateway-cineca' ),
				),
				'api_pwd_test'           => array(
					'title'       => __( 'Local API password', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'Password of the account enabled to the use of the plugin\'s api', 'wp-pagopa-gateway-cineca' ),
				),
				'id_payment_model_test'  => array(
					'title'       => __( 'Payment model ID', 'wp-pagopa-gateway-cineca' ),
					'type'        => 'text',
					'description' => __( 'ID of payment model related to the e-commerce defined on the Cineca back office', 'wp-pagopa-gateway-cineca' ),
				),
			);

		}

		/**
		 * Options list for the field payment_conf_method.
		 *
		 * @return array
		 */
		public function get_options_conf_method() {
			$contab_ids = array(
				'SYNC-INT'  => __( 'Polling on PagoAtenei', 'wp-pagopa-gateway-cineca' ),
				'ASYNC-EXT' => __( 'Asycnhronous notification by PagoAtenei', 'wp-pagopa-gateway-cineca' ),
			);
			return $contab_ids;
		}

		/**
		 * Validate the fields of the payment form.
		 *
		 * @return boolean
		 */
		public function validate_fields() {
			// Checkout fields should be validate earlier. That is in the checkout phase.
			return true;
		}

		/**
		 * Process the payment.
		 *
		 * @param int $order_id - 'The ID of the order'.
		 * @return array - 'Redirect page'.
		 */
		public function process_payment( $order_id ) {

			// Retrieve the order details.
			$order       = new WC_Order( $order_id );
			$log_manager = new Log_Manager( $order );
			if ( DEBUG_MODE_ENABLED ) {
				error_log( '@@@ Process the order ewith the id: ' . $order_id );
			}

			// During the transaction the order is "on-hold".
			$log_manager->log( STATUS_PAYMENT_SUBMITTED );

			// Create the payment position on the Cineca gateway.
			$this->gateway_controller = new Gateway_Controller();
			// Init the gateway.
			$init_result = $this->gateway_controller->init( $order );

			// Check if the gateway is connected.
			if ( 'KO' === $init_result['code'] ) {
				// Error initializing the gateway.
				$error_msg  = __( 'Gateway connection error.', 'wp-pagopa-gateway-cineca' );
				$error_desc = $error_msg . ' - ' . $init_result['msg'];
				$log_manager->log( STATUS_PAYMENT_NOT_CREATED, null, $error_desc );
				wc_add_notice( $error_msg, 'error' );
				$redirect_url = wc_get_checkout_url();
				return array(
					'result'   => 'failed',
					'redirect' => $redirect_url,
				);
			}

			// Load the payment position.
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
			// Add the Iuv as metadata to the order.
			$order->update_meta_data( '_iuv', $payment_position['iuv'] );
			// Add a note to the order with the Iuv code.
			$note = __( 'The Iuv of the order is:', 'wp-pagopa-gateway-cineca' );
			$note = $note . ' ' . $payment_position['iuv'];
			$order->add_order_note( $note );
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
		 *
		 * @throws Exception( 'Invalid token' ) token if the passed token is not valid.
		 */
		public function webhook_payment_complete( $args ) {
			$token      = ( ! empty( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '' );
			$id_session = ( ! empty( $_GET['idSession'] ) ? sanitize_text_field( wp_unslash( $_GET['idSession'] ) ) : '' );
			$outcome    = ( ! empty( $_GET['esito'] ) ? sanitize_text_field( wp_unslash( $_GET['esito'] ) ) : '' );
			if ( '' === $token ) {
				echo 'Invalid request';
				exit;
			}

			$par_array = Gateway_Controller::extract_token_parameters( $token );

			// Retrieve the parameters from the token.
			try {
				$order_id = $par_array[0];
				$iuv      = $par_array[1];
				if ( ( ! $order_id ) || ( ! $iuv ) ) {
					throw new Exception( 'Invalid token' );
				}
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
			$p_found = $log_manager->check_payment_status( $order_id, $iuv, STATUS_PAYMENT_CREATED );
			if ( ! $p_found ) {
				// Error checking the parameters passed by the gateway.
				$error_msg = __( 'The status of the payment is not consistent', 'wp-pagopa-gateway-cineca' );
				$error_msg = $error_msg . ' n. ' . $order_id;
				// $log_manager->log( STATUS_PAYMENT_CANCELLED, null, $error_msg );
				$this->log_action( 'error', $error_msg );
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
				if ( $outcome ) {
					$error_msg = $error_msg . ' - ' . __( 'Outcome', 'wp-pagopa-gateway-cineca' ) . ': ' . $outcome;
				}
				if ( $id_session ) {
					$error_msg = $error_msg . ' - ' . __( 'IdSession', 'wp-pagopa-gateway-cineca' ) . ': ' . $id_session;
				}
				$log_manager->log( STATUS_PAYMENT_CANCELLED, null, $error_msg );
				$this->error_redirect( $error_msg );
				return;
			}

			// Payment executed.
			$log_manager->log( STATUS_PAYMENT_EXECUTED, $iuv );

			// Check if confirmation should be requested.
			$options                  = get_option( 'woocommerce_pagopa_gateway_cineca_settings' );
			$synchronous_confirmation = ( 'SYNC-INT' === $options['payment_conf_method'] ) ? true : false;
			$confirm_payment          = ( 'yes' === $options['confirm_payment'] ) ? true : false;

			$confirmed    = false;
			$num_attempts = 1;

			if ( $confirm_payment ) {
				// Confirmation required.
				sleep( 2 );
				for ( $num_attempts; ( false === $confirmed ) && ( $num_attempts <= WAIT_NUM_ATTEMPTS ); $num_attempts++ ) {
					// Ask the status of the payment to the gateway.
					$this->gateway_controller = new Gateway_Controller();
					// Init the gateway.
					$init_result = $this->gateway_controller->init( $order );
					// Check if the gateway is connected.
					if ( 'KO' === $init_result['code'] ) {
						// Error initializing the gateway.
						$error_msg  = __( 'Gateway connection error.', 'wp-pagopa-gateway-cineca' );
						$error_desc = $error_msg . ' - ' . $init_result['msg'];
						$log_manager->log( STATUS_PAYMENT_NOT_CONFIRMED, $iuv, $error_desc );
						$this->error_redirect( $error_msg );
						return;
					}

					// Check the status of the payment.
					$payment_status = $this->gateway_controller->get_payment_status();

					if ( DEBUG_MODE_ENABLED ) {
						error_log( '@@@ Attempts: ' . $num_attempts );
						$this->log_action( 'info', print_r( $payment_status, true ) );
					}
					if ( $payment_status && ( 'OK' === $payment_status['code'] ) && ( 'ESEGUITO' === $payment_status['msg'] ) ) {
						// Payment executed, exit from the loop.
						$confirmed = true;
						break;
					} elseif ( $payment_status && ( 'OK' === $payment_status['code'] ) && ( 'NON_ESEGUITO' === $payment_status['msg'] ) ) {
						// Payment not yet executed wait and retry.
						sleep( WAIT_NUM_SECONDS );
						$confirmed = false;
					} else {
						// Error reported by the gateway, exit from the loop.
						$confirmed = false;
						break;
					}
				}

				$num_attempts = $num_attempts <= WAIT_NUM_ATTEMPTS ? $num_attempts : $num_attempts - 1;

			} else {
				// // Confirmation NOT required
				$confirmed = true;
			}

			if ( ! ( $confirmed ) ) {
				// Payment not confirmed.
				$error_msg  = __( 'Payment not confirmed by the gateway. Please contact the staff, the order number is:', 'wp-pagopa-gateway-cineca' );
				$error_msg  = $error_msg . ' ' . $order_id . ' - Iuv: ' . $iuv;
				$error_desc = $error_msg . ' - Code:' . $payment_status['code'];
				$error_desc = $error_desc . ' - Status: ' . $payment_status['msg'];
				$error_desc = $error_desc . ' - Attempts: ' . $num_attempts;
				$log_manager->log( STATUS_PAYMENT_NOT_CONFIRMED, $iuv, $error_desc );
				$this->error_redirect( $error_msg );
				return;
			} else {
				// Payment confirmed.
				if ( $synchronous_confirmation ) {
					// Payment synchronously confirmed.
					$order->payment_complete();
					$log_desc = 'Attempts: ' . $num_attempts;
					if ( DEBUG_MODE_ENABLED ) {
						$this->log_action( 'info', $log_desc );
					}
					$log_manager->log( STATUS_PAYMENT_CONFIRMED, $iuv, $log_desc );
					$redirect_url = $this->get_return_url( $order );
					wp_safe_redirect( $redirect_url );
				} else {
					// Payment asynchronously confirmed: will be confirmed by Pagoatenei calling pagopa_notifica_transazione.
					$redirect_url = wc_get_checkout_url();
					$log_desc     = __( 'Processing the payment', 'wp-pagopa-gateway-cineca' );
					$log_manager->log( STATUS_PAYMENT_WAITING_CONFIRM, $iuv, $log_desc );
					$redirect_url = $this->get_return_url( $order );
					wp_safe_redirect( $redirect_url );
				}
			}

		}

		/**
		 * Hook called from the gateway to notify a payment.
		 *
		 * @param array $args - Incominc parameters.
		 * @return void
		 */
		public function webhook_transaction_notification( $args ) {
			$this->log_action( 'info', '@@@ webhook_transaction_notification @@@' );
			$this->verifyAPIAuthentication();
			$result    = trim( file_get_contents( 'php://input' ) );
			// SimpleXML seems to have problems with the colon ":" in the <xxx:yyy> response tags, so take them out.
			$xml      = preg_replace( '/(<\/?)(\w+):([^>]*>)/', '$1$2$3', $result );
			$xml      = simplexml_load_string( $xml );
			$json     = wp_json_encode( $xml );
			$response = json_decode( $json, true );

			try {
				if ( $response ) {
					$cod_applicazione    = $response['soapBody']['ns2paNotificaTransazione']['codApplicazione'];
					$cod_versamento_ente = $response['soapBody']['ns2paNotificaTransazione']['codVersamentoEnte'];
					$iuv                 = $response['soapBody']['ns2paNotificaTransazione']['transazione']['iuv'];
					$cod_dominio         = $response['soapBody']['ns2paNotificaTransazione']['transazione']['codDominio'];
					$stato               = $response['soapBody']['ns2paNotificaTransazione']['transazione']['stato'];

					// $log_msg  = '@@@ Source data sent by PagoAtenei:' . $result ;
					// $this->log_action( 'info', $log_msg );
					$this->log_action( 'info', '@@@ codApplicazione: ' . $cod_applicazione );
					$this->log_action( 'info', '@@@ iuv: ' . $iuv );
					$this->log_action( 'info', '@@@ stato: ' . $stato );
					$this->log_action( 'info', '@@@ cod_versamento_ente: ' . $cod_versamento_ente );

					if ( 'RT_ACCETTATA_PA' === $stato ) {
						// Check payment data.
						$options = get_option( 'woocommerce_pagopa_gateway_cineca_settings' );
						$esito   = $response['soapBody']['ns2paNotificaTransazione']['transazione']['esito'];
						$this->log_action( 'info', '@@@ esito: ' . $esito );
						if ( ( 'ASYNC-EXT' === $options['payment_conf_method'] ) &&
							( $cod_applicazione === $options['application_code'] ) &&
							( $cod_dominio === $options['domain_code'] ) &&
							( 'PAGAMENTO_ESEGUITO' === $esito ) &&
							$iuv ) {
							// Try to retrieve the order.
							$order_id    = Gateway_Controller::extract_order_number( $options['order_prefix'], $cod_versamento_ente );
							$order       = new WC_Order( $order_id );
							$log_manager = new Log_Manager( $order );
							$p_found     = $log_manager->check_payment_status( $order->get_id(), $iuv, STATUS_PAYMENT_CREATED );
							if ( $p_found ) {
								// Set the order as paid.
								$order->payment_complete();
								$log_manager->log( STATUS_PAYMENT_CONFIRMED_BY_NOTIFICATION, $iuv );
								$this->log_action( 'info', 'Payment confirmed by notification. Order: ' . $cod_versamento_ente );
							} else {
								$this->log_action( 'warning', 'Payment already confirmed or payment not found. Order: ' . $cod_versamento_ente );
							}
						} else {
							$this->log_action( 'warning', 'Payment status in : paNotificaTransazione:' . $stato . ' - Order: ' . $cod_versamento_ente );
						}
					}
				}
			} catch ( Exception $e ) {
				$this->log_action( 'error', 'Error in paNotificaTransazione:' . $e->getMessage() );
			}
			// In any case return OK.
			header( 'Content-type: text/xml; charset=utf-8' );
			echo NOTIFY_TRANSACTION_RESPONSE;
			exit();
		}

		/**
		 * Check authorization to use the API.
		 *
		 * @return boolen - True if the account is right.
		 */
		private function verifyAPIAuthentication() {
			// $this->log_action( 'info', json_encode( apache_request_headers() ) );
			$api_username  = $this->api_user;
			$api_password  = $this->api_pwd;
			if ( $api_username && $api_username ) {
				$auth          = apache_request_headers();
				$authorization = isset( $auth['Authorization'] ) ? $auth['Authorization'] : '';
				$valid_token   = 'Basic ' . base64_encode( $api_username . ':' . $api_password );
				if ( $authorization !== $valid_token ) {
					header( 'HTTP/1.1 401 Unauthorized' );
					exit;
				}
			}
			return true;
		}

		/**
		 * Hook called to start scheduled actions.
		 *
		 * @param array $args - Arguments of the function.
		 * @return void
		 */
		public function webhook_scheduled_actions( $args ) {
			$token              = ( ! empty( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '' );
			$this->log_action( 'info', 'webhook_scheduled_actions Token:' . $token );
			// Check if the token is present.
			if ( ! $token ) {
				echo 'Invalid token';
				exit;
			}
			// Check the API is enabled.
			$options = get_option( 'woocommerce_pagopa_gateway_cineca_settings' );
			if ( empty( $options['api_token'] ) || ( '' === $options['api_token'] ) ) {
				echo 'API  disabled';
				exit;
			}
			// Check the token validity.
			$this->log_action( 'info', 'API Token:' . $options['api_token'] );
			if ( $options['api_token'] !== $token ) {
				echo 'Invalid token';
				exit;
			}

			// Action1: Update the status of the orders.
			$result = $this->update_orders_status();

			// Actions2: Add here other actions...

			echo 'OK';
			exit();
		}

		/**
		 * Check the status of 'on-hold' orders on the gateway and if they have been paid change the status of the order to 'processing'.
		 *
		 * @return string
		 */
		private function update_orders_status() {
			// Get all the on-hold orders of the last NUM_DAYS_TO_CHECK days in the 'on-hold' status.
			$this->log_action( 'info', '[Cron] Started the procedure to check orders status.' );
			$diff_string  = '-' . NUM_DAYS_TO_CHECK . ' day';
			$initial_date = gmdate( 'Y-m-d', strtotime( $diff_string, strtotime( 'today' ) ) );
			$final_date   = gmdate( 'Y-m-d' );
			$final_date   = gmdate( 'Y-m-d' );
			$date_created = $initial_date . '...' . $final_date;
			$orders       = wc_get_orders(
				array(
					'limit'        => -1,
					'type'         => 'shop_order',
					'status'       => array( 'wc-pending' ),
					'date_created' => $date_created,
				),
			);
			$this->log_action( 'info', '[Cron] Orders to update: ' . count( $orders ) );

			// Looop all pending orders.
			foreach ( $orders as $order ) {
				// Create the gateway controller.
				$gateway_controller = new Gateway_Controller();
				// Init the gateway.
				$init_result = $gateway_controller->init( $order );
				$log_manager = new Log_Manager( $order );

				// Check the status of the order.
				$payment_status = $gateway_controller->get_payment_status();

				if ( $payment_status && ( 'OK' === $payment_status['code'] ) && ( 'ESEGUITO' === $payment_status['msg'] ) ) {
					// Change the status of the paid order to 'processing'.
					$order->payment_complete();
					$iuv = $order->get_meta( '_iuv' );
					$log_manager->log( STATUS_PAYMENT_CONFIRMED_BY_SCRIPT, $iuv );
					// Send an email.
					$this->send_processing_notification_mail( $order );
				}
			}
			$this->log_action( 'info', '[Cron] Ended the procedure to check orders status.' );
			return 'OK';
		}

		/**
		 * Send processing notification email.
		 *
		 * @param object $order - The order.
		 * @return void
		 */
		public function send_processing_notification_mail( $order ) {
			$body     = sprintf( __( 'The order n. %s has been paid. Please, manage the order.', 'wp-pagopa-gateway-cineca' ), $order->get_order_number() );
			$subject  = __( 'Payment of the order n.', 'wp-pagopa-gateway-cineca' );
			$subject  = $subject . ' ' . $order->get_order_number();
			$receiver = 'ilclaudio@gmail.com';

			// Get woocommerce mailer.
			$mailer   = WC()->mailer();
			$emails   = $mailer->get_emails();
			$receiver = $emails['WC_Email_New_Order']->recipient;
			$this->log_action( 'info', 'Sending email to:' . $receiver );

			// Wrap message using woocommerce html email template.
			$heading         = false;
			$wrapped_message = $mailer->wrap_message( $heading, $body );
			// Create new WC_Email instance.
			$wc_email = new WC_Email();
			// Style the wrapped message with woocommerce inline styles.
			$html_message = $wc_email->style_inline( $wrapped_message );

			// Send e-mail using WP mail function.
			$mailer->send( $receiver, $subject, $html_message, HTML_EMAIL_HEADERS, '' );
		}

		/**
		 * Redirect on the checkout page when an error occurs.
		 *
		 * @param string $error_msg - The error message shown to the customer.
		 * @return void
		 */
		private function error_redirect( $error_msg ) {
			wc_add_notice( $error_msg, 'error' );
			$this->log_action( 'error', $error_msg );
			$redirect_url = wc_get_checkout_url();
			wp_safe_redirect( $redirect_url );
		}

		/**
		 * Log actions.
		 *
		 * @param string $log_type -The message severity.
		 * @param string $message - The message log.
		 * @return void
		 */
		public function log_action( $log_type, $message ) {
			if ( DEBUG_MODE_ENABLED ) {
				error_log( $message );
			}
			$logger  = wc_get_logger();
			$context = array( 'source' => self::get_plugin_name() );
			$logger->log( $log_type, $message, $context );
		}

		/**
		 * Return the name of the plkugin.
		 *
		 * @return string - The name of the plugin.
		 */
		public static function get_plugin_name() {
			return 'wp-pagopa-gateway-cineca';
		}

		/**
		 * Return the url of the plugin.
		 *
		 * @return  string
		 */
		public static function get_plugin_url() {
			return get_site_url() . '/wp-content/plugins/wp-pagopa-gateway-cineca';
		}

		/**
		 * Enqueue the css and the js files.
		 *
		 * @return void
		 */
		public static function enqueue_scripts() {
			$plugin_name    = self::get_plugin_name();
			$file_path      = self::get_plugin_url() . '/assets/css/wp-pagopa-gateway-cineca.css';
			$plugin_data    = get_plugin_data( __FILE__ );
			$plugin_version = $plugin_data['Version'];
			wp_enqueue_style( $plugin_name . 'css-1', $file_path, null, $plugin_version );
		}

	} // end plugin class
}


/**
 * Creates the menu of the plugin.
 *
 * @return void
 */
function add_plugin_menu() {
	$required_role = 'edit_themes';
	$title         = __( 'PagoPA Gateway', 'wp-pagopa-gateway-cineca' );
	load_plugin_textdomain( 'wp-pagopa-gateway-cineca', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	$trans_manager = new Transaction_Manager();
	add_submenu_page(
		'woocommerce',
		$title,
		__( 'PagoPA Transactions', 'wp-pagopa-gateway-cineca' ),
		$required_role,
		'wc-edizioni-sns-activations-page',
		array( $trans_manager, 'admin_show_transactions_page' ),
		30
	);
}

