# <img src="docs/Logo.png" width=50> PagoPA Gateway 
**PagoPa Gateway** is a **WooCommerce plugin** for integration with **PagoPA Cineca payment portal**.

It is a payment gateway than can be used on a site implemented with *WordPress* and *WooCommerce*.

**PagoPa Gateway** allows the customers of your e-commerce to pay with **PagoPA** using a **credit card** or printing the payment notice and paying it **offline**.

The project was born from the need to integrate the payment method PagoPA into the site "Edizioni" ([edizioni.sns.it](https://edizioni.sns.it)) using the [portale dei pagamenti PagoPA Cineca](https://sns.pagoatenei.cineca.it/).


## Project status
Beta testing

## Features
- Pay order using PagoPA.
- Form to configure the connection to the Cineca gateway.
- Test and production distinct configurations.
- Management of payment worflow.
- A schedulable action to manage and update the orders paid offline.
- Internationalization of messages and labels.

## Getting started
1. First ask Cineca to activate the service and to activate the test and the production enviroment. They will give you the following data for both the test and the production enviroment:
   - The Api username.
   - The Api password.
   - The WSDL of the service.
   - An SSL certificate with the related passphrase.
2. Check if the software requirements are satisfied (see the "*Software requirements*" paragraph of this file).
3. Download, install and configure the plugin as described in the "*Installation and configuration*" paragraph of this document.

## Software requirements
1. The Wordpress CMS (version >= 5.6.6).
2. The WooCommerce plugin (version >= 5.0.0) for WordPress.
3. The web server Apache with *mod_ssl* and *soap* extension installed and enabled.
## Installation and configuration
1. [Download](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/archive/refs/heads/main.zip) the last stable version of the plugin.
2. Unzip the content into the wp-content/plugins folder.
3. Activate the plugin from the administration interface of Wordpress. 
4. Configure the plugin from the administration interface of Worpdress. The following fields must be specified:
   - **Enable/disable**: the flag to enable the payment gateway.
   - **Title**: the name of the gateway, will be shown in the checkout page.
   - **Description en**: a description for the payment method, will be shown in the english version of the checkout page.
   - **Description it**: a description for the payment method, will be shown in the italian version of the checkout page.
   - **Enable/Disable test mode**: the flag to enable the test mode.
   - **Aplication code**: the application code assigned by Cineca.
   - **Domain code**: the Vat code of the institution.
   - **Iban**: the Iban of the institution.
   - **Accounting type**: accounting code as defined in the PagoPA taxonomy (https://github.com/pagopa/pagopa-api/blob/develop/taxonomy/tassonomia.json).
   - **Payment model ID**: the ID of the payment model defined in the Cineca backoffice related to the payments coming from the e-commerce. This model will be accessible also from the Cineca frontoffice.
   - **Certificate name**: the name of the *pem* certificate provided by Cineca. If the certificate has a *pk12* format it must be converted to the *pem* format.
   - **Certificate password**: the password of the certificate provided by Cineca.
   - **Order prefix**: a prefix that is added to the WP order number before being sent to the gateway. You can leave it empty. It is useful if you use multiple instances of the site in test or dev enviroments to keep separate the orders of the various instances.
   - **Encryption key**: the key used to encrypt the token passed to the gateway.
   - **API token**: the token used to start the scheduled actions and the REST API. If empty these features are disabled.
  
  **Production credentials**
   - **Cineca front end url**: the url of the front end of PagoAtenei. It is provided by Cineca.
   - **Base url of the API**: the base url of the PagoAtenei Soap web services. It is provided by Cineca.
   - **Username of the API**: the username to use the Soap web services. It is provided by Cineca.
   - **Password of the API**: the password to use the Soap web services. It is provided by Cineca.
 
  **Test credentials**
   - **Cineca front end url**: the url of the front end of PagoAtenei. It is provided by Cineca.
   - **Base url of the API**: the base url of the PagoAtenei Soap web services. It is provided by Cineca.
   - **Username of the API**: the username to use the Soap web services. It is provided by Cineca.
   - **Password of the API**: the password to use the Soap web services. It is provided by Cineca. 

## Gallery
![Enable](docs/screenshots/EnablePlugin_1.png)
**Image 1:** Enable the plugin

![configure](docs/screenshots/ConfigurePlugin_1.png) 
**Image 2:** Configure the plugin

## Documentation
- Check the docs folder of the plugin.
- Check the setup/TestSoap for the SoapUI project to test the Soap api.
- Check the Cineca site for further Api documents:
	- [Modalità di Integrazione](https://wiki.u-gov.it/confluence/pages/releaseview.action?pageId=329846832)
	- [WS pago-ATENEI Applicazioni](https://wiki.u-gov.it/confluence/display/public/UGOVINT/WS+pago-ATENEI+Applicazioni)

## Demo
### Docker
You can test the plugin using a Docker container that contains all the software components needed (Wordpress + WooCommerce + wp-pagopa-gateway-cineca). See this Docker file: 


## Repository
This repository contains the source code of the project.
## License
The project is under the GPL-3.0-only license as found in the [LICENSE](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/blob/main/LICENSE) file.

## How to contribute
The main purpose of this repository is to continue evolving the plugin. We want to make contributing to this project as easy and transparent as possible, and we are grateful to the community for contributing bugfixes and improvements.
## Copyright
1. Detentore copyright: Scuola Normale Superiore
2. Responsabili del progetto: Michele Fiaschi, Claudio Battaglino, Alida Isolani, Marcella Monreale
