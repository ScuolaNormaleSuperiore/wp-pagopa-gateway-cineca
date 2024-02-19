const wpgw_settings = window.wc.wcSettings.getSetting( 'wp_gateway_pagopa_data', {} );
const wpgw_label = window.wp.htmlEntities.decodeEntities( wpgw_settings.title ) || window.wp.i18n.__( 'WP Gateway PagoPa', 'pagopa_gateway_cineca' );
const WPGW_Content = () => {
    return window.wp.htmlEntities.decodeEntities( wpgw_settings.description || '' );
};
const WPGW_Block_Gateway = {
    name: 'pagopa_gateway_cineca',
    label: wpgw_label,
    content: Object( window.wp.element.createElement )( WPGW_Content, null ),
    edit: Object( window.wp.element.createElement )( WPGW_Content, null ),
    canMakePayment: () => true,
    ariaLabel: wpgw_label,
    supports: {
        features: wpgw_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( WPGW_Block_Gateway );