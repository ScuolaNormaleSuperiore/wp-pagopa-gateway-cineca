# <img src="docs/Logo.png" width=50> PagoPA Gateway 
**WooCommerce plugin** for integration with **PagoPA Cineca payment portal**.

It is a payment gateway than can be used on a site implemented with *WordPress* and *WooCommerce*.

The project was born from the need to integrate the payment method PagoPA into the site "Edizioni" ([edizioni.sns.it](https://edizioni.sns.it)) using the [portale dei pagamenti PagoPA Cineca](https://sns.pagoatenei.cineca.it/).


## Project status
Beta testing

## Features
- Pay order using PagoPA.
- Form to configure the connection to the Cineca gateway.
- Test and production distinct configurations.
- Management of payment worflow

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
3. The web server Apache with mod_ssl and soap extension installed and enabled.
## Installation and configuration
1. [Download](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/archive/refs/heads/main.zip) the last stable version of the plugin.
2. Unzip the content into the wp-content/plugins folder.
3. Activate the plugin from the administration interface of Wordpress. 
4. Configure the plugin from the administration interface of Worpdress. The following fields must be specified:
   - **Enable/disable**: the flag to enable the payment gateway.
   - **Title**: the name of the gateway, will be shown in the checkout page.
   - **Description**: a description for the gateway, will be shown in the checkout page.

## Gallery
![Home](docs/screenshots/EnablePlugin_1.png)
_**Image 1:** Enable the plugin_

![Home](docs/screenshots/ConfigurePlugin_1.png)
_**Image 2:** Configure the plugin_


## Documentation
- Check the docs folder of the plugin.
- Check the setup/TestSoap for the SoapUI project to test the Soap api.
- Check the Cineca site for further Api documents:
	- [Modalità di Integrazione](https://wiki.u-gov.it/confluence/pages/releaseview.action?pageId=329846832)
	- [WS pago-ATENEI Applicazioni](https://wiki.u-gov.it/confluence/display/public/UGOVINT/WS+pago-ATENEI+Applicazioni)

## Payment worflow implemented

## Demo
### Docker
You can test the plugin using the enviroment (Wordpress + WooCommerce + wp-pagopa-gateway-cineca) included in the docker file included into this project.


## Repository
This repository contains the source code of the project.
## License
The project is under the GPL-3.0-only license as found in the [LICENSE](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/blob/main/LICENSE) file.

## How to contribute
The main purpose of this repository is to continue evolving the plugin. We want to make contributing to this project as easy and transparent as possible, and we are grateful to the community for contributing bugfixes and improvements.
## Copyright
1. Detentore copyright: Scuola Normale Superiore
2. Responsabili del progetto: Michele Fiaschi, Claudio Battaglino, Alida Isolani, Marcella Monreale
