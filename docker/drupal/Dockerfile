# This image contains Debian's Apache httpd in conjunction with PHP8.x
# (as mod_php) and uses mpm_prefork by default.
# https://hub.docker.com/_/php
FROM php:8.4-apache

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
RUN apt-get -y install zip unzip git

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install current official release of Drupal in /var/www/drupal.
# (Permit Drupal's Composer installer/scaffold plugins during the root build.)
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer create-project \
      --no-interaction \
      --prefer-dist \
      drupal/recommended-project:~11.4.12 \
      /var/www/drupal \
    && echo "Checking Drupal installation..." \
    && ls -la /var/www/drupal/web \
    && test -f /var/www/drupal/web/core/lib/Drupal.php \
    && test -f /var/www/drupal/web/index.php \
    && echo "Drupal core installed successfully"


# Change the Apache document root to point to the drupal install
ENV APACHE_DOCUMENT_ROOT=/var/www/drupal/web

# Update the default Apache virtual host.
RUN sed -ri \
    "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
    /etc/apache2/sites-available/*.conf

# Allow Drupal's .htaccess rewrite rules and enable mod_rewrite.
RUN printf '%s\n' \
    "<Directory ${APACHE_DOCUMENT_ROOT}>" \
    "    Options FollowSymLinks" \
    "    AllowOverride All" \
    "    Require all granted" \
    "</Directory>" \
    > /etc/apache2/conf-available/drupal.conf \
    && a2enconf drupal \
    && a2enmod rewrite

RUN a2enmod rewrite

# Install Drush
RUN cd /var/www/drupal && composer require drush/drush

# Avoid SSL certificate requirment on MariaDB client
RUN mkdir -p /var/www/drupal/drush \
    && printf '%s\n' \
      'command:' \
      '  sql:' \
      '    options:' \
      "      extra: '--skip-ssl'" \
      > /var/www/drupal/drush/drush.yml

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

# When a drupal container is started, we need to execute a setup script.
COPY setup.sh /usr/local/bin/setup.sh
RUN chmod +x /usr/local/bin/setup.sh
CMD ["/usr/local/bin/setup.sh"]