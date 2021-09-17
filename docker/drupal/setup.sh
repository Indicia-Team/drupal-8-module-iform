#!/bin/sh

# The script specified in Dockerfile to be executed at 
#container start up.

# Wait till database is up before going any further.
echo "Waiting for MySql."
until mysql -u user -ppassword -h mysql -e "SELECT VERSION()"; do
    sleep 1
done

# Call the original entry point of the image to start apache.
docker-php-entrypoint apache2-foreground