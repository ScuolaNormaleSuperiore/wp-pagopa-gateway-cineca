<?php

/**
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

 
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'wp_gateway_pagopa_add' );
function wp_gateway_pagopa_add( $gateways ) {
	$gateways[] = 'WP_Gateway_PagoPa';
	return $gateways;
}

add_action( 'plugins_loaded', 'wp_gateway_pagopa_init' );
function wp_gateway_pagopa_init() {

    /**
     * Add the gateway(s) to WooCommerce.
     */
    class WP_Gateway_PagoPa extends WC_Payment_Gateway {

        function __construct() {

            
        }
    }
}