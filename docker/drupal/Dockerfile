# This image contains Debian's Apache httpd in conjunction with PHP8.0
# (as mod_php) and uses mpm_prefork by default.
# https://hub.docker.com/_/php
FROM php:8.0-apache
# Use PHP development configuration file
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
# Add a tool to assist with installing PHP extensions.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions
# Install required extensions that are not already present in image.
RUN install-php-extensions gd xdebug mysqli pdo_mysql
# Configure the xdebug extension for single-step
# debugging of code in the container.
#ENV XDEBUG_CONFIG=discover_client_host=1
ENV XDEBUG_CONFIG=client_host=172.17.0.1
ENV XDEBUG_MODE=debug

# Install unzip - needed for Drupal extraction.
RUN apt-get update
RUN apt-get -y install zip unzip

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN chmod a+x composer.phar
RUN mv composer.phar /usr/local/bin/composer

# Install current official release of Drupal in /var/www/drupal.
# For the latest 8.9 version, use drupal/recommended-project:^8.9, 
# or drupal/recommended-project:^8 for latest Drupal 8 version.
RUN composer create-project drupal/recommended-project /var/www/drupal

# Change the Apache document root to point to the drupal install
ENV APACHE_DOC_ROOT /var/www/drupal/web
RUN sed -ri -e 's!/var/www/html!${APACHE_DOC_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOC_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Drush
RUN cd /var/www/drupal && composer require drush/drush

# Install MySql client
RUN apt-get -y install default-mysql-client

# Create a user the same identity as the host user so that it can 
# share files on the bind mount containing the drupal code which 
# docker-compose is going to set up.
ARG UID
ARG GID
ARG USER
ARG GROUP
RUN groupadd -g ${GID} ${GROUP}
RUN useradd -u ${UID} -g ${GID} ${USER}
# Store the args in the environment for use at run time.
ENV UID=${UID}
ENV GID=${GID}
ENV USER=${USER}
ENV GROUP=${GROUP}

# Make sure there is a drupal files folder to set permissions on.
RUN mkdir /var/www/drupal/web/sites/default/files
# Change ownership of all Drupal files (which have been installed by root)
# so that they are owned by the host user for easy debugging.
RUN cd /var/www/drupal && \
    chown -R ${USER}:${GROUP} .
# Make the web files accessible to the apache group.
RUN cd /var/www/drupal/web && \
    chown -R ${USER}:www-data .
# Set default web directory permissions according to 
# https://www.drupal.org/docs/security-in-drupal/securing-file-permissions-and-ownership
RUN cd /var/www/drupal/web && \
    find . -type d -exec chmod u=rwx,g=rx,o= '{}' \;
# Set default web file permissions 
RUN cd /var/www/drupal/web && \
    find . -type f -exec chmod u=rw,g=r,o= '{}' \;
# Give apache extra permissions to write to the sites/default/files folder
RUN chmod g=rwx /var/www/drupal/web/sites/default/files
# This won't permit you to install new modules through the web interface,
# http://localhost:8090/admin/modules/install. That is fine because we should
# use Composer, 
# https://www.drupal.org/docs/extending-drupal/installing-modules#s-add-a-module-with-composer
# However, for dev, let's allow it
RUN cd /var/www/drupal/web && \
    chmod g=rwx modules themes
RUN cd /var/www/drupal/web/sites && \
    chown www-data default

# When a drupal container is started, we need to execute a setup script.
COPY setup.sh /var/www/drupal
CMD /var/www/drupal/setup.sh