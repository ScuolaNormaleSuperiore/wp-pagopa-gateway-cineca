(function() {
	"use strict";
	const settings = window.wc.wcSettings.getSetting( 'wp_gateway_pagopa_data', {} );
	const title = settings.title || '';
	const icon = settings.icon;
	const label = window.wp.htmlEntities.decodeEntities(title);
	const supports = settings.supports || '';
	const Content = () => {
			return window.wp.htmlEntities.decodeEntities( settings.description || '' );
	};
	console.log(settings);
	const Block_Gateway = {
			name: settings.id,
			label: label,
			content: Object( window.wp.element.createElement )( Content, null ),
			edit: Object( window.wp.element.createElement )( Content, null ),
			canMakePayment: () => true,
			ariaLabel: label,
			supports: {
				features: settings.supports,
			},
			supports: {
					features: supports,
			},
			icon: icon || '',
	};
	window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );
})();
