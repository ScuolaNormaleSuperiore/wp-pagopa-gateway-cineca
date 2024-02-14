##############################################################################
######			USEFUL DOCKER COMMANDS:
######
######		- docker build -t myshop-img -f Dockerfile .
######		- docker run -p 80:80 -p 3306:3306 --name=myshop -d myshop-img
######		- docker exec -it myshop /bin/bash
######
##############################################################################

###### Start from an official Php release with Apache ######
FROM php:8.2.14-apache


################# CONFIGURATIONS (define ARG after FROM) #####################
ARG WP_ARCHIVE=wordpress-6.4.3.tar.gz
ARG WC_ZIP_FILE=woocommerce.8.5.2.zip
ARG SF_THEME_FILE=storefront.4.5.4.zip
ARG ADMINER_FILE=v4.8.1/adminer-4.8.1.php
##############################################################################


# Update packages 
RUN apt-get update && apt-get install
# Install useful packages
RUN apt-get install -y \
	vim \
	git \
	bash-completion \
	libxml2-dev \
	unzip \
	wget

# Install and enable useful modules
RUN docker-php-ext-install mysqli && \
	docker-php-ext-install soap && \
	a2enmod rewrite

# Copy the file of the database files.
COPY configure/createUserAndDatabase.sql /tmp/createUserAndDatabase.sql
COPY src/myshopdb.sql /tmp/myshopdb.sql
COPY configure/configureWP.sql /tmp/configureWP.sql
# Copy source code and configure.
COPY configure/startServicesScript.sh /root/startServicesScript.sh
COPY src/uploads.zip /tmp/uploads.zip
COPY configure/wp-config.php /tmp/wp-config.php
COPY configure/.htaccess /tmp/.htaccess

# Install mariadb, create and init the database.
RUN apt install mariadb-server -y && \
	/etc/init.d/mariadb start  && \
	mysql < /tmp/createUserAndDatabase.sql && \
	mysql myshop < /tmp/myshopdb.sql && \
	mysql myshop < /tmp/configureWP.sql

# Install Wordpress, WooCommerce and the plugin
# WORKDIR /tmp
RUN curl -o /tmp/${WP_ARCHIVE} https://wordpress.org/${WP_ARCHIVE} && \
	rm -rf /var/www/html && \
	tar -xvzf /tmp/${WP_ARCHIVE} --directory /var/www/ && \
	mv /var/www/wordpress /var/www/html	&& \
	curl -o /tmp/${WC_ZIP_FILE} https://downloads.wordpress.org/plugin/${WC_ZIP_FILE} && \
	unzip /tmp/${WC_ZIP_FILE} -d /var/www/html/wp-content/plugins/ && \
	curl -o /tmp/${SF_THEME_FILE} https://downloads.wordpress.org/theme/${SF_THEME_FILE} && \
	unzip /tmp/${SF_THEME_FILE} -d /var/www/html/wp-content/themes/ && \
	cd /var/www/html/wp-content/plugins && \
	git clone https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca.git && \
	cp -f /tmp/wp-config.php  /var/www/html/wp-config.php && \
	cp /tmp/.htaccess /var/www/html/.htaccess && \
	unzip /tmp/uploads.zip -d /var/www/html/wp-content/uploads/ && \
	wget https://github.com/vrana/adminer/releases/download/${ADMINER_FILE}  -O /var/www/html/adminer.php && \
	chown -R www-data:www-data /var/www/html/*

# Remove the temp file.
RUN rm -rf /tmp/*

EXPOSE 80 3306

######  Start Server Services with the bash shell ######
ENTRYPOINT ["/bin/bash"]
CMD ["/root/startServicesScript.sh"]

###DEBUG: ENTRYPOINT ["tail", "-f", "/dev/null"]