<?php 
/**
* @file
* Defines a predefined configuration for a link to the BRC test warehouse.
*/

global $_iform_warehouses;

$_iform_warehouses['brc-testwarehouse'] = array(
  'title' => t('BRC Test Warehouse'),
  'base_url' => 'http://testwarehouse.indicia.org.uk/',
  'geoserver_url' => 'http://testwarehouse.indicia.org.uk:8080/geoserver/'
);