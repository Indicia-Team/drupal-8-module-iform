# drupal-8
Drupal 8 modules for Indicia

# Docker
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

