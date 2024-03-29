(function () {
  "use strict";
  const wpElement = window.wp.element;
  const wcBlockRegistry = window.wc.wcBlocksRegistry;
  const settings = window.wc.wcSettings.getSetting(
    "wp_gateway_pagopa_data",
    {}
  );
  const title = settings.title || "";
  const icon = settings.icon;
  const icon_list_txt = settings.icon_list;
  const label = window.wp.htmlEntities.decodeEntities(title);
  const description = settings.description || "";
  const supports = settings.supports || "";

  // const Content = () => {
  // 		return window.wp.htmlEntities.decodeEntities( description );
  // };

  // console.log(settings);

  const Block_Gateway = {
    name: settings.id,
    label: wpElement.createElement(() =>
      wpElement.createElement(
        "span",
        null,
        wpElement.createElement("img", {
          src: icon,
          alt: title,
        }),
        "  " + title
      )
    ),
    // content: Object( wpElement.createElement )( Content, null ),
    // edit: Object( wpElement.createElement )( Content, null ),
    content: wpElement.createElement(() =>
      wpElement.createElement("span", {
        dangerouslySetInnerHTML: {
          __html: `
										<div class="additional-html">${icon_list_txt}</div>
										${description}
								`,
        },
      })
    ),
    edit: wpElement.createElement(() =>
      wpElement.createElement("span", {
        dangerouslySetInnerHTML: {
          __html: `
										<div class="additional-html">${icon_list_txt}</div>
										${description}
								`,
        },
      })
    ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
      features: settings.supports,
    },
    supports: {
      features: supports,
    },
    icon: icon || "",
  };
  wcBlockRegistry.registerPaymentMethod(Block_Gateway);
})();
