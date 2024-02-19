(function() => {
	"use strict";
	const settings = window.wc.wcSettings.getSetting( 'wp_gateway_pagopa_data', {} );
	const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'WP Gateway PagoPa', 'pagopa_gateway_cineca' );
	const Content = () => {
			return window.wp.htmlEntities.decodeEntities( settings.description || '' );
	};
	const Block_Gateway = {
			name: 'pagopa_gateway_cineca',
			label: label,
			content: Object( window.wp.element.createElement )( Content, null ),
			edit: Object( window.wp.element.createElement )( Content, null ),
			canMakePayment: () => true,
			ariaLabel: label,
			supports: {
					features: settings.supports,
			},
	};
	window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );
})();
