##############################################################################
######			USEFUL DOCKER COMMANDS:
######
######		- docker build -t myshop-img -f Dockerfile .
######		- docker run -p 80:80 -name=myshop -d myshop-img
######		- docker run -p 9980:80 -p 3380:3306 --name=myshop -d myshop-img
######		- docker exec -it myshop /bin/bash
######
##############################################################################

################# CONFIGURATIONS #############################################
ARG WP_ARCHIVE='wordpress-5.8.2.tar.gz'
ARG WC_ZIP_FILE='woocommerce.5.9.0.zip'
##############################################################################

###### Start from an official Php release with Apache ######
FROM php:7.4.3-apache


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
COPY createUserAndDatabase.sql /tmp/createUserAndDatabase.sql
COPY myshopdb.sql /tmp/myshopdb.sql
COPY configureWP.sql /tmp/configureWP.sql
# Copy source code and scripts.
COPY startServicesScript.sh /root/startServicesScript.sh

# Install mariadb, create and init the database.
RUN apt-get install -y mariadb-server  && \
	/usr/sbin/service mysql start && \
	mysql < /tmp/createUserAndDatabase.sql && \
	mysql myshop < /tmp/myshopdb.sql && \
	mysql myshop < /tmp/configureWP.sql

RUN echo ${WP_ARCHIVE}

# Install Wordpress, WooCommerce and the plugin
# WORKDIR /tmp
RUN curl -o /tmp/wordpress-5.8.2.tar.gz https://wordpress.org/wordpress-5.8.2.tar.gz && \
	rm -rf /var/www/html && \
	tar -xvzf /tmp/wordpress-5.8.2.tar.gz --directory /var/www/ && \
	mv /var/www/wordpress /var/www/html	&& \
	curl -o /tmp/woocommerce.5.9.0.zip https://downloads.wordpress.org/plugin/woocommerce.5.9.0.zip && \
	unzip /tmp/woocommerce.5.9.0.zip -d /var/www/html/wp-content/plugins 

# Configure Wordpress
COPY wp-config.php /var/www/html/

# Remove the temp file.
#RUN rm -rf /tmp/*

EXPOSE 80

######  Start Server Services with the bash shell ######
ENTRYPOINT ["/bin/bash"]
CMD ["/root/startServicesScript.sh"]