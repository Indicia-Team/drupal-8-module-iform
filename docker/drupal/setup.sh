#!/bin/sh

# The script specified in Dockerfile to be executed at 
# container start up.
set -e

# Wait till database is up before going any further.
echo "Waiting for MySql."
until mysql -u user --skip-ssl -ppassword -h mysql -e "SELECT VERSION()"; do
    sleep 1
done

# Make sure there is a drupal files folder to set permissions on.
mkdir -p /var/www/drupal/web/sites/default/files

# Give apache extra permissions to write to the sites/default/files folder
chown -R www-data:www-data /var/www/drupal/web/sites/default/files
chmod -R 2775 /var/www/drupal/web/sites/default/files

# This won't permit you to install new modules through the web interface,
# http://localhost:8091/admin/modules/install. That is fine because we should
# use Composer, 
# https://www.drupal.org/docs/extending-drupal/installing-modules#s-add-a-module-with-composer
# However, for dev, let's allow it
cd /var/www/drupal/web && \
    chown www-data:www-data modules themes && \
    chmod 2775 modules themes

cd /var/www/drupal/web/sites && \
    chown www-data:www-data default

# Create settings.php and configure
if [ ! -f /var/www/drupal/web/sites/default/settings.php ]; then
    cp /var/www/drupal/web/sites/default/default.settings.php \
        /var/www/drupal/web/sites/default/settings.php

    # Optional add initial settings that want to be treated as 
    # Removed trusted host patterns warning
    printf '\n%s\n' \
        '$settings["trusted_host_patterns"] = ["^localhost$"];' \
        >> /var/www/drupal/web/sites/default/settings.php
fi

chown www-data:www-data /var/www/drupal/web/sites/default/settings.php
chmod 664 /var/www/drupal/web/sites/default/settings.php

# Call the original entry point of the image to start apache.
exec docker-php-entrypoint apache2-foreground