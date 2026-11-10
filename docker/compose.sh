#!/bin/sh
set -eu

export HOST_UID="$(id -u)"
export HOST_GID="$(id -g)"
export HOST_USER="$(id -un)"
export HOST_GROUP="$(id -gn)"

docker compose build --no-cache --progress=plain
docker compose up -d

# Wait for Drupal to be up
echo "Waiting for Drupal..."
until curl --silent --output outputfile http://localhost:8091; do
  sleep 1
done
echo "Drupal is up."

# Find out where we get redirected to on requesting localhost:8091.
location=$(curl \
  --output outputfile \
  --location \
  --silent \
  --show-error \
  --write-out "%{url_effective}" \
  http://localhost:8091)

echo $location

if [ $location = "http://localhost:8091/core/install.php" ]; then
  # Database initialisation has not been performed yet.
  echo
  prompt="Do you want the Drupal website initialised (Y/n)?"
  read -rs -n 1 -p "$prompt" 
  if [ "$REPLY" = "Y" ] || [ "$REPLY" = "y" ] || [ -z "$REPLY" ]; then
    echo
    # Complete the standard Drupal installation.
    echo "...Executing drush site:install on the server."
    docker compose exec -T drupal sh -c '
      set -e
      cd /var/www/drupal

      vendor/bin/drush site:install standard \
        --db-url="mysql://user:password@mysql/drupal" \
        --account-name=admin \
        --account-pass=password \
        -y

      vendor/bin/drush theme:install olivero claro -y
      vendor/bin/drush config:set system.theme default olivero -y
      vendor/bin/drush config:set system.theme admin claro -y

      vendor/bin/drush config:set system.performance css.preprocess false \
        --input-format=yaml -y

      vendor/bin/drush config:set system.performance js.preprocess false \
        --input-format=yaml -y

      vendor/bin/drush cr
    '

    # Install dependencies of drupal-8-moudule-iform
    echo "...Installing required modules."
    docker exec docker-drupal-1 sh -c '
      cd /var/www/drupal 
      composer require drupal/jquery_ui
      composer require drupal/jquery_ui_tabs
      composer require drupal/jquery_ui_draggable
    '

    # Enable drupal-8-module-iform
    echo "...Enabling modules."
    docker exec docker-drupal-1 sh -c '
      cd /var/www/drupal

      # currently needed for new (rather than upgraded) Drupal 11.3+ installs
      # to provide field.storage.node.body compatibility as used in iForm.
      vendor/bin/drush en node_storage_body_field -y

      vendor/bin/drush en jquery_ui jquery_ui_tabs jquery_ui_draggable iform_inlinejs iform
    '
    echo
    echo "Log in with user 'admin' and password 'password' then"
    echo "go to http://localhost:8091/admin/config/iform/settings"
    echo "to configure your warehouse settings."
  else
    echo
    echo "You need to set up Drupal."
  fi
fi

# Clean up
rm -f outputfile

echo
echo "You can visit the Drupal site at http://localhost:8091"
