<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.2.0
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WP_Gateway_PagoPa_Blocks extends AbstractPaymentMethodType
{
	private $gateway;
	protected $name = 'wp_gateway_pagopa';

	public function initialize() {
		$this->settings = get_option('woocommerce_wp_gateway_pagopa_settings', []);
		$this->gateway = new WP_Gateway_PagoPa();
	}

	public function is_active() {
		return $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'wp_gateway_pagopa-blocks-integration',
			plugin_dir_url( __FILE__ ) . '../assets/js/checkout.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			null,
			true
		);
		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations('wp_gateway_pagopa-blocks-integration');
		}
		return ['wp_gateway_pagopa-blocks-integration'];
	}

	public function get_payment_method_data()
	{
		return [
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
		];
	}
}
