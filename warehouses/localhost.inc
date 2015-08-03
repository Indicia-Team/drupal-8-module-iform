<?php 
/**
* @file
* Defines the configuration for a predefined link to a localhost warehouse running in a
* development environment.
*/

global $_iform_warehouses;

$_iform_warehouses['localhost'] = array(
  'title' => t('http://localhost/indicia (for developing on a local webserver running at this URL)'),
  'base_url' => 'http://localhost/indicia/',
  'geoserver_url' => 'http://localhost:8080/geoserver/'
);