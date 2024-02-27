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
 * Version: 1.2.0
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

	include( plugin_dir_path( __FILE__ ) . 'class-gateway.php' );
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



// ///////// CHECKOUT BLOCKS MANAGEMENT //////////


/**
 * Custom function to declare compatibility with cart_checkout_blocks feature.
 */
function pagopa_gateway_declare_cart_checkout_blocks_compatibility() {
	// Check if the required class exists.
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			// Declare compatibility for 'cart_checkout_blocks'.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

// Hook the custom function to the 'before_woocommerce_init' action.
add_action( 'before_woocommerce_init', 'pagopa_gateway_declare_cart_checkout_blocks_compatibility' );

// Hook the custom function to the 'woocommerce_blocks_loaded' action.
add_action( 'woocommerce_blocks_loaded', 'pagopa_gateway_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type.
 */
function pagopa_gateway_register_order_approval_payment_method_type() {
	// Check if the required class exists.
	if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
	}

	// Include the custom Blocks Checkout class.
	require_once plugin_dir_path( __FILE__ ) . 'inc/class-block.php';

	// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action.
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				// Register an instance of My_Custom_Gateway_Blocks.
				$payment_method_registry->register( new WP_Gateway_PagoPa_Blocks );
		}
	);
}
