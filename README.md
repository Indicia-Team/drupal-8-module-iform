# drupal-8
Drupal 8 modules for Indicia

# Development

## Docker Compose
If you are developing on Linux and have Docker installed, you can
```
cd docker
./compose.sh
```
to start a development environment consisting of two containers.
One runs the MySQL database while the other runs an Apache web server with
Drupal installed and accessible at http://localhost:8090.

If you allow the script to configure Drupal, it will set up the database,
install required modules, and enable this IForm module. The code in this
direcotry is mounted on the host so that changes you make take immediate
effect (caching permitting.)

The container uses PHP 8 and has xdebug enabled so that you can attach a
debugger and single step through the code or set breakpoints.

Your Drupal site configuration is preserved in Docker volumes even if the
containers are destroyed. If you do want to wipe everything and start again
the `reset.sh` script will do that for you.

## DDEV
The contemporary (2025) way to create an environment for Drupal module
development is to use [DDEV](https://ddev.com/). Once you have it installed then

1. Git clone this module from Github.

1. At a command prompt, change to the folder where you just cloned the module
and run `ddev start`.

1. Run `ddev poser`. You may need to follow instructions to obtain an access
token from Github. Having entered the token there may be a minute or two wait
before you seen any response - be patient.

   - When asked to trust php-http/discovery, respond with y(es).
   - When asked to trust tbachert/spi, respond with n(o)

1. Run `ddev symlink-project`.

1. Run `ddev config --update`

1. Run `ddev restart`

1. Run `git submodule update --init --recursive`

1. With a browser, navigate to the url given by DDEV, probably
http://iform.ddev.site and complete the normal Drupal
installation

Additional ddev commands are courtesy of https://github.com/ddev/ddev-drupal-contrib. Go there to find more commands to help your development.

For information on step debugging, see the
[configuration instructions](https://ddev.readthedocs.io/en/latest/users/debugging-profiling/step-debugging/).

## Connecting to a development warehouse

If you have used Docker to run up a local warehouse then you can configure
things to connect it for iForm development.

First add the Drupal web server to the warehouse network with
`docker network connect {indicia_network_name}{drupal_container_name}` e.g.
`docker network connect indicia_default ddev-iform-web`

In Admin > Configuration > Indicia Settings, set
the Warehouse URL to `http://warehouse_container_name:warehouse_port/` e.g.
`http://indicia-warehouse-1:8080/`. Similarly you can set the Geoserver URL,
e.g.`http://indicia-warehouse-1:8090/geoserver/`

You will get a curl error about SSL if you are using https to browse the Drupal site and the warehouse is only using http. To work around this, switch to
browsing the Drupal site with http.

You will get JavaScript errors when your browser tries to make Ajax calls to the
warehouse. A work around to this is to add the warehouse to your /etc/hosts
file. To get the IP address of the warehouse run

```
docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' warehouse_container_name`
```

Now add a line to /etc/hosts like `warehouse_ip warehouse_container_name` e.g.
`172.19.0.8 indicia-warehouse-1`


