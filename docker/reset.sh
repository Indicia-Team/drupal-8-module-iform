#!/bin/bash

prompt="This will destroy your Drupal site and erase all data. Proceed (y/N)?"
read -rs -n 1 -p "$prompt" 
echo
if [ "$REPLY" = "Y" ] || [ "$REPLY" = "y" ]; then

    # Stop and remove containers and volumes.
    docker-compose down -v
else
    echo Reset abandonned.
fi