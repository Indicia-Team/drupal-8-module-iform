
docker-compose build \
  --build-arg UID=$(id -u) \
  --build-arg GID=$(id -g) \
  --build-arg USER=$(id -un) \
  --build-arg GROUP=$(id -gn)
docker-compose up -d

# Wait for Drupal to be up
echo "Waiting for Drupal..."
until curl --silent --output outputfile http://localhost:8090; do
  sleep 1
done
echo "Drupal is up."

# Find out where we get redirected to on requesting localhost:8090.
location=$(curl \
  --output outputfile \
  --location \
  --silent \
  --show-error \
  --write-out "%{url_effective}" \
  http://localhost:8090)

if [ $location = "http://localhost:8090/core/install.php" ]; then
  # Database initialisation has not been performed yet.
  echo
  prompt="Do you want the Drupal website initialised (Y/n)?"
  read -rs -n 1 -p "$prompt" 
  if [ "$REPLY" = "Y" ] || [ "$REPLY" = "y" ] || [ -z "$REPLY" ]; then
    echo
    # Complete the standard Drupal installation.
    echo "...Executing drush site:install on the server."
    docker exec docker_drupal_1 sh -c '
      cd /var/www/drupal 
      vendor/bin/drush site:install standard \
        --db-url="mysql://user:password@mysql/drupal" \
        --account-pass=password
    '

    # Fix warning about trusted hosts.
    echo "...Setting trusted hosts."
    docker exec docker_drupal_1 sh -c '
      cd /var/www/drupal/web/sites/default
      chown $USER:www-data settings.php
    '
    cat << 'EOF' | docker exec -i docker_drupal_1 sh
      cd /var/www/drupal/web/sites/default
      chmod u+w settings.php
      echo '$settings["trusted_host_patterns"] = ["^localhost$"];' >> settings.php
      chmod u-w settings.php
EOF

    # Install dependencies of drupal-8-moudule-iform
    echo "...Installing required modules."
    docker exec docker_drupal_1 sh -c '
      cd /var/www/drupal 
      composer require drupal/jquery_ui
      composer require drupal/jquery_ui_tabs
    '

    # Enable drupal-8-module-iform
    echo "...Enabling modules."
    docker exec docker_drupal_1 sh -c '
      cd /var/www/drupal 
      vendor/bin/drush en jquery_ui jquery_ui_tabs iform_inlinejs iform
    '
    echo
    echo "Log in with user 'admin' and password 'password' then"
    echo "go to http://localhost:8090/admin/config/iform/settings"
    echo "to configure your warehouse settings."
  else
    echo
    echo "You need to set up Drupal."
  fi
fi

# Clean up
rm -f outputfile

echo
echo "You can visit the Drupal site at http://localhost:8090"
