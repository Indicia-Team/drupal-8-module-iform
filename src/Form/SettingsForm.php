<?php

/**
 * @file
 * Contains \Drupal\iform\Form\SettingsForm.
 */

namespace Drupal\iform\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;

/**
 * Indicia settings form.
 */
class SettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iform_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    \iform_load_helpers(['map_helper', 'data_entry_helper']);
    $config = \Drupal::config('iform.settings');
    global $_iform_warehouses;
    $this->loadWarehouseArray();
    foreach ($_iform_warehouses as $warehouse => $def) {
      $warehouses[$warehouse] = $def['title'];
    }
    $warehouses['other'] = t('Other');
    $form['warehouse'] = [
      '#type' => 'radios',
      '#title' => t('Indicia Warehouse'),
      '#options' => $warehouses,
      '#description' => t('Select the Indicia Warehouse to connect to, or select Other and enter the details in the Warehouse URL and GeoServer URL fields.'),
      '#required' => TRUE,
      '#default_value' => $config->get('warehouse'),
    ];
    $form['other_warehouse'] = [
      '#type' => 'details',
      '#attributes' => ['id' => 'warehouse_details'],
      '#open' => $config->get('warehouse') === 'other',
      '#title' => t('Other Warehouse Details')
    ];
    $form['other_warehouse']['base_url'] = [
      '#type' => 'textfield',
      '#title' => t('Warehouse URL'),
      '#description' => t('If selecting Other for the Indicia Warehouse option, please enter the URL of the Indicia Warehouse you are connecting to, otherwise ignore this setting. ' .
        'This should include the full path and trailing slash but not the index.php part, e.g. "http://www.mysite.com/indicia/".'),
      '#maxlength' => 255,
      '#required' => FALSE,
      '#default_value' => $config->get('base_url'),
    ];
    $form['other_warehouse']['geoserver_url'] = [
      '#type' => 'textfield',
      '#title' => t('GeoServer URL'),
      '#description' => t('If selecting Other for the Indicia Warehouse option, please enter the URL of the GeoServer instance you are connecting to, otherwise ignore this setting. ' .
        'This is optional, if not specified then you will not be able to use some of the advanced mapping facilities provided by GeoServer.'),
      '#maxlength' => 255,
      '#required' => FALSE,
      '#default_value' => $config->get('geoserver_url'),
    ];
    $form['private_warehouse'] = [
      '#type' => 'checkbox',
      '#title' => t('Warehouse is private'),
      '#description' => t('If your warehouse is not publicly visible (e.g. behind a firewall) then as long as it accepts requests from the IP address of the Drupal website\'s server ' .
        'you can tick this box to send requests to the warehouse via a proxy on the Drupal server.'),
      '#required' => FALSE,
      '#default_value' => $config->get('private_warehouse'),
    ];
    $form['allow_connection_override'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow website connection override'),
      '#description' => t('Tick this box to allow forms to override the specified website ID and password on an individual basis. This allows a single Drupal installation '.
        'to support forms which link to multiple Indicia website registrations. Leave unticked if you are not sure.'),
      '#required' => FALSE,
      '#default_value' => $config->get('allow_connection_override'),
    ];
    $form['website_id'] = [
      '#type' => 'textfield',
      '#title' => t('Indicia Website ID'),
      '#description' => t('Please enter the numeric ID given to your website record when your website was registered on the Indicia Warehouse.'),
      '#size' => 10,
      '#maxlength' => 10,
      '#required' => TRUE,
      '#default_value' => $config->get('website_id'),
    ];
    // Require the password only if not previously set.
    $pwd_required = ($config->get('password') == '');
    if ($pwd_required) {
      $pwd_description = t('Please enter the password specified when your website was registered on the Indicia Warehouse.');
    }
    else {
      $pwd_description = t('If you need to change it, enter the password specified when your website was registered on the Indicia Warehouse. ' .
        'Otherwise leave the password blank to keep your previous settings.');
    }
    $form['password'] = [
      '#type' => 'password_confirm',
      '#description' => $pwd_description,
      '#required' => $pwd_required,
    ];
    $baseTheme = $config->get('base_theme');
    $form['base_theme'] = [
      '#type' => 'select',
      '#title' => t('Optimise output for base theme'),
      '#description' => 'If using a supported base theme, select it here.',
      '#required' => TRUE,
      '#default_value' => $baseTheme ? $baseTheme : 'generic',
      '#options' => [
        'generic' => 'Generic theme output',
        'bootstrap-3' => 'Bootstrap 3 optimised output',
      ],
    ];

    $form['esproxy'] = [
      '#type' => 'details',
      '#title' => t('Elasticsearch configuration'),
      '#open' => TRUE,
    ];
    $instruct = <<<TXT
You can configure a default connection for reporting in Elasticsearch here. This will typically be for open access
reporting data available across your site rather than a connection for specific tasks such as verification. This
connection will be used by any blocks that use the indicia.datacomponents library to link to Elasticsearch. IForm pages
that link to Elasticsearch may have their own connection details. See the <a
href="https://indicia-docs.readthedocs.io/en/latest/developing/rest-web-services/elasticsearch.html">
Indicia Elasticsearch documentation</a> for more info.
TXT;
    $form['esproxy']['instructions'] = [
      '#markup' => '<p>' . t($instruct) . '</p>'
    ];
    $esVersion = $config->get('elasticsearch_version');
    $authMethod = $config->get('elasticsearch_auth_method');
    $form['esproxy']['elasticsearch_version'] = [
      '#type' => 'radios',
      '#title' => t('Elasticsearch version'),
      '#description' => t('Elasticsearch major version number.'),
      '#options' => [
        '6' => '6.x',
        '7' => '7.x',
      ],
      '#required' => TRUE,
      '#default_value' => $esVersion ? $esVersion : '6',
    ];
    $form['esproxy']['elasticsearch_endpoint'] = [
      '#type' => 'textfield',
      '#title' => t('Elasticsearch endpoint'),
      '#description' => t('Elasticsearch endpoint declared in the REST API.'),
      '#required' => FALSE,
      '#default_value' => $config->get('elasticsearch_endpoint'),
    ];
    // @todo Replace following with a documentation link.
    $description = <<<TXT
When authentication as a client, the warehouse administrator must configure the REST API and provide a user and secret
that should be entered into the configuration below. When authenticating as a user using Java Web Tokens, you must
generate an RSA public/private key pair, add the public key to the warehouse's configuration for your website and
save the private key in a file called rsa_private.pem in the Drupal private files directory. The REST API must also
be configured to provide access to Elasticsearch for the jwtUser authentication method.
TXT;
    $form['esproxy']['elasticsearch_auth_method'] = [
      '#type' => 'radios',
      '#title' => t('Elasticsearch authentication method'),
      '#description' => t('Elasticsearch major version number.'),
      '#options' => [
        'directClient' => 'Authenticate as a client configured in the Warehouse REST API',
        'jwtUser' => 'Authenticate as the logged in user using Java Web Tokens',
      ],
      '#required' => TRUE,
      '#default_value' => $authMethod ? $authMethod : 'directClient',
      '#desctription' => $this->t($description),
    ];
    $form['esproxy']['elasticsearch_user'] = [
      '#type' => 'textfield',
      '#title' => t('Elasticsearch user'),
      '#description' => t('REST API user with Elasticsearch access.'),
      '#required' => FALSE,
      '#default_value' => $config->get('elasticsearch_user'),
      '#states' => [
        // Show this control only if the directClient auth method selected.
        'visible' => [
          ':input[name="elasticsearch_auth_method"]' => ['value' => 'directClient'],
        ],
      ],
    ];
    $form['esproxy']['elasticsearch_secret'] = [
      '#type' => 'textfield',
      '#title' => t('Elasticsearch secret'),
      '#description' => t('REST API user secret.'),
      '#required' => FALSE,
      '#default_value' => $config->get('elasticsearch_secret'),
      '#states' => [
        // Show this control only if the directClient auth method selected.
        'visible' => [
          ':input[name="elasticsearch_auth_method"]' => ['value' => 'directClient'],
        ],
      ],
    ];
    $form['esproxy']['elasticsearch_warehouse_prefix'] = [
      '#type' => 'textfield',
      '#title' => t('Warehouse prefix'),
      '#description' => t('Prefix given to Indicia IDs on this Elasticsearch index to form a unique document _id. ' .
        'Required if this connection will allow any update operations (e.g. for verification status changes), or can ' .
        'be provided as a setting on each individual page that allows updates.'),
      '#required' => FALSE,
      '#default_value' => $config->get('elasticsearch_warehouse_prefix'),
    ];
    $form['esproxy']['elasticsearch_all_records_permission'] = [
      '#type' => 'textfield',
      '#title' => 'Elasticsearch all records permission',
      '#description' => $this->t('Permission required to access all records via this connection.'),
      '#required' => FALSE,
      '#default_value' => empty($config->get('elasticsearch_all_records_permission')) ? 'indicia data admin' : $config->get('elasticsearch_all_records_permission'),
    ];
    $form['esproxy']['elasticsearch_my_records_permission'] = [
      '#type' => 'textfield',
      '#title' => 'Elasticsearch my records permission',
      '#description' => $this->t('Permission required to access a user\'s own records via this connection. Normally safe to leave as "access iform content"'),
      '#required' => FALSE,
      '#default_value' => empty($config->get('elasticsearch_my_records_permission')) ? 'access iform content' : $config->get('elasticsearch_my_records_permission'),
    ];
    $form['esproxy']['elasticsearch_location_collation_records_permission'] = [
      '#type' => 'textfield',
      '#title' => 'Elasticsearch location collation records permission',
      '#description' => $this->t('Permission required to access records in a collation area (e.g. Local Record Centre boundary) via this connection.'),
      '#required' => FALSE,
      '#default_value' => empty($config->get('elasticsearch_location_collation_records_permission')) ? 'indicia data admin' : $config->get('elasticsearch_location_collation_records_permission'),
    ];

    $form['api_keys'] = [
      '#type' => 'details',
      '#title' => t('API Keys'),
      '#open' => TRUE,
    ];
    $form['api_keys']['google_api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Google Place Search API Key'),
      '#description' => t('The Google API provides the Places API text search, another option to lookup place names when you use the place search control. ' .
        'It references a global database of places and returns the list of possibilities with their spatial references ' .
        'to Indicia. To obtain your own API key for the Google Place Search API, please visit <a target="_blank" href="https://code.google.com/apis/console">' .
        'the Google APIs Console</a>. '),
      '#required' => FALSE,
      '#default_value' => $config->get('google_api_key'),
    ];
    $form['api_keys']['google_maps_api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Google Maps API Key'),
      '#description' => t('The Google API provides the Places API text search, another option to lookup place names when you use the place search control. ' .
        'It references a global database of places and returns the list of possibilities with their spatial references ' .
        'to Indicia. To obtain your own API key for the Google Maps JavaScript API, please visit <a target="_blank" href="https://code.google.com/apis/console">' .
        'the Google APIs Console</a>. '),
      '#required' => FALSE,
      '#default_value' => $config->get('google_maps_api_key'),
    ];
    $form['api_keys']['bing_api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Bing API Key'),
      '#description' => t('The Bing API key is required to allow use of Bing map layers but can be left blank if you do not intend ' .
        'to use Bing maps. To obtain your own key, please visit the <a target="_blank" href="http://www.bingmapsportal.com/">Bing Maps Account Center</a>. ' .
        'Please ensure that you read and adhere to the <a href="http://www.microsoft.com/maps/product/terms.html">terms of use</a>.'),
      '#required' => FALSE,
      '#default_value' => $config->get('bing_api_key'),
    ];
    $form['api_keys']['os_api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Ordnace Survey API Key'),
      '#description' => t('The Ordnance Survey API key is required to allow use of OS map layers but can be left blank ' .
        'if you do not intend to use OS maps. To obtain your own key, please visit ' .
        '<a target="_blank" href="https://developer.ordnancesurvey.co.uk/os-maps-api-enterprise">OS Maps API for ' .
        'Enterprise</a>. There is a free trial but this is a paid-for service.'),
      '#required' => FALSE,
      '#default_value' => $config->get('os_api_key'),
    ];
    $form['map'] = [
      '#type' => 'details',
      '#title' => t('Map Settings'),
      '#open' => TRUE
    ];
    $form['map']['instruct'] = [
      '#markup' => '<p>' . t('Pan and zoom this map to set the default map position for your survey input and mapping pages.') . '</p>'
    ];
    // kill the JavaScript wrap as Drupal 8 doesn't like outputting the JS under #markup
    global $indicia_templates;
    $indicia_templates['jsWrap'] = '{content}';
    $form['map']['panel'] = [
      '#type' => 'inline_template',
      '#template' => \map_helper::map_panel([
        'width' => '100%',
        'height' => 500,
        'presetLayers' => ['osm'],
        'editLayer' => FALSE,
        'layers' => [],
        'initial_lat' => $config->get('map_centroid_lat'),
        'initial_long' => $config->get('map_centroid_long'),
        'initial_zoom' => $config->get('map_zoom'),
        'standardControls' => ['panZoomBar'],
        'scroll_wheel_zoom' => 'false',
      ]),
    ];
    $form['map']['map_centroid_lat'] = [
      '#attributes' => ['id' => 'edit-map-centroid-lat'],
      '#type' => 'hidden',
      '#default_value' => $config->get('map_centroid_lat'),
    ];
    $form['map']['map_centroid_long'] = [
      '#attributes' => ['id' => 'edit-map-centroid-long'],
      '#type' => 'hidden',
      '#default_value' => $config->get('map_centroid_long', -1),
    ];
    $form['map']['map_zoom'] = [
      '#attributes' => ['id' => 'edit-map-zoom'],
      '#type' => 'hidden',
      '#default_value' => $config->get('map_zoom', 6),
    ];
    $form['map']['spatial_ref_systems'] = [
      '#type' => 'details',
      '#title' => t('List of spatial or grid reference systems'),
      '#description' => 'Please tick off each spatial or grid reference system you wish to enable for input when using this website.',
      '#open' => TRUE,
    ];
    $systems = [
      'OSGB' => t('British National Grid'),
      'OSIE' => t('Irish National Grid'),
      '4326' => t('GPS Latitude and Longitude (WGS84)'),
      'guernsey' => t('Guernsey Grid'),
      'jersey' => t('Jersey Grid'),
      'utm30ed50' => t('UTM 30N (ED50)'),
      'utm30wgs84' => t('UTM 30N (WGS84)'),
      '2169' => t('LUREF Luxembourg'),
      '3006' => t('SWEREF99 TM / Swedish Transverse Mercator'),
      '3021' => t('RT90 2.5 gon v / Swedish Grid'),
    ];
    $selected_systems = $this->formValuesFromSrefSystems($systems, $config);
    $form['map']['spatial_ref_systems']['spatial_ref_systems_list'] = [
      '#type' => 'checkboxes',
      '#default_value' => $selected_systems['list'],
      '#options' => $systems,
    ];
    $form['map']['spatial_ref_systems']['spatial_ref_systems_other'] = [
      '#type' => 'textfield',
      '#title' => t('Other'),
      '#default_value' => $selected_systems['other'],
      '#description' => t('For any system not in this list, you can enter a comma separated list of EPSG codes or other system names as long as they are recognised by the Indicia Warehouse you are using.'),
    ];
    $form['master_checklist_id'] = [
      '#type' => 'textfield',
      '#title' => t('Master checklist ID'),
      '#description' => t('The species checklist ID used as an all species hierarchy.'),
      '#default_value' => $config->get('master_checklist_id'),
    ];
    $form['profile_location_type_id'] = [
      '#type' => 'textfield',
      '#title' => t('Profile location type ID'),
      '#description' => t("The ID of the location type for the main location layer that can be associated with user profiles to indicate a user's preferences."),
      '#default_value' => $config->get('profile_location_type_id'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];
    $form['#attached']['library'][] = 'iform/admin';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = \Drupal::configFactory()->getEditable('iform.settings');
    if ($values['warehouse'] === '' || ($values['warehouse'] === 'other' && empty($values['base_url']))) {
      $form_state->setErrorByName(
        'warehouse',
        t('Please supply a warehouse URL for connection to Indicia, or select a pre-configured connection.')
      );
    }
    elseif (!empty($values['password'])) {
      // Test the connection to the warehouse.
      $urls = self::getWarehouseUrls($values);
      \data_entry_helper::$base_url = $urls['base_url'];
      // Clear the cache if the linked warehouse changes.
      if ($config->get('base_url') !== $urls['base_url']) {
        \data_entry_helper::clear_cache();
      }
      try {
        $read_auth = \data_entry_helper::get_read_auth($values['website_id'], $values['password']);
        $test = \data_entry_helper::get_population_data([
          'table' => 'survey',
          'extraParams' => $read_auth + ['limit' => 0],
          'nocache' => TRUE,
        ]);
        if (isset($test['error'])) {
          $form_state->setErrorByName(
            'website_id',
            $this->t('The configuration for the connection to the warehouse is incorrect. This could be an incorrect or unavailable Indicia Warehouse, an incorrect Indicia Website ID or Password.')
          );
        }
      }
      catch (\Exception $e) {
        $form_state->setErrorByName(
          'warehouse',
          $e->getMessage()
        );
      }
    }
    $systems = $this->srefSystemsFromForm($values);
    if (empty($systems)) {
      // @todo This error does not get shown properly, possibly a Drupal 8 beta bug?
      $form_state->setErrorByName(
        'spatial_ref_systems',
        t('Please enable at least one spatial or grid reference system.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('iform.settings');
    $values = $form_state->getValues();
    $config->set('warehouse', $values['warehouse']);
    $urls = self::getWarehouseUrls($values);
    $config->set('base_url', $urls['base_url']);
    $config->set('geoserver_url', $urls['geoserver_url']);
    $config->set('private_warehouse', $values['private_warehouse']);
    $config->set('allow_connection_override', $values['allow_connection_override']);
    $config->set('website_id', $values['website_id']);
    if (!empty($values['password'])) {
      $config->set('password', $values['password']);
    }
    $config->set('base_theme', $values['base_theme']);
    $config->set('elasticsearch_version', $values['elasticsearch_version']);
    $config->set('elasticsearch_endpoint', $values['elasticsearch_endpoint']);
    $config->set('elasticsearch_auth_method', $values['elasticsearch_auth_method']);
    $config->set('elasticsearch_user', $values['elasticsearch_user']);
    $config->set('elasticsearch_secret', $values['elasticsearch_secret']);
    $config->set('elasticsearch_warehouse_prefix', $values['elasticsearch_warehouse_prefix']);
    $config->set('elasticsearch_all_records_permission', $values['elasticsearch_all_records_permission']);
    $config->set('elasticsearch_my_records_permission', $values['elasticsearch_my_records_permission']);
    $config->set('elasticsearch_location_collation_records_permission', $values['elasticsearch_location_collation_records_permission']);
    $config->set('google_api_key', $values['google_api_key']);
    $config->set('google_maps_api_key', $values['google_maps_api_key']);
    $config->set('bing_api_key', $values['bing_api_key']);
    $config->set('os_api_key', $values['os_api_key']);
    $config->set('map_centroid_lat', $values['map_centroid_lat']);
    $config->set('map_centroid_long', $values['map_centroid_long']);
    $config->set('map_zoom', $values['map_zoom']);
    $config->set('master_checklist_id', $values['master_checklist_id']);
    $config->set('profile_location_type_id', $values['profile_location_type_id']);
    // Save any indicia variables declared by hook_variable_info.
    global $language;
    /*
     * @todo Implement extended variables properly
     *
    $vars = module_invoke_all('variable_info', array("language" => $language));
    foreach ($vars as $var=>$config) {
      if (!empty($config['addToIndiciaSettingsPage']) && $config['addToIndiciaSettingsPage'] && isset($values[$var]))
        $config->set($var, $values[$var]);
    }
     */

    $systems = $this->srefSystemsFromForm($values);
    $config->set('spatial_systems', $systems);
    $config->save();
    drupal_set_message(t('Indicia settings saved.'));
  }

  /**
   * Get the list of predefined warehouse configurations.
   *
   * Utility function to populate the list of warehouses in the global
   * $_iform_warehouses. Each warehouse is loaded from an .inc file in the
   * warehouses sub-folder.
   *
   * @todo Remove need for global
   */
  private function loadWarehouseArray() {
    global $_iform_warehouses;
    $_iform_warehouses = [];
    foreach (glob(drupal_get_path('module', 'iform') . '/warehouses/*.inc') as $warehouse_file) {
      require $warehouse_file;
    }
  }

  /**
   * Returns the base url and geoserver url defined in the submitted form.
   */
  private function getWarehouseUrls($values) {
    if (strcasecmp($values['warehouse'], t('Other')) === 0) {
      return [
        'base_url' => $values['base_url'],
        'geoserver_url' => $values['geoserver_url'],
      ];
    }
    else {
      global $_iform_warehouses;
      $this->loadWarehouseArray();
      foreach ($_iform_warehouses as $warehouse => $def) {
        if ($warehouse === $values['warehouse']) {
          return [
            'base_url' => $def['base_url'],
            'geoserver_url' => $def['geoserver_url'],
          ];
        }
      }
      // If not found, something went wrong.
      throw new Exception('Could not find configuration for selected warehouse.');
    }
  }

  /**
   * Get sref systems from the form data.
   *
   * Convert the values in the form array for spatial reference systems into
   * the correct comma separated format for Indicia.
   */
  private function srefSystemsFromForm($values) {
    $arr = [];
    // Convert the form value array into a simple array of enabled items.
    foreach ($values['spatial_ref_systems_list'] as $sys => $enabled) {
      if ($enabled) {
        $arr[] = $sys;
      }
    }
    $other = trim($values['spatial_ref_systems_other']);
    if (!empty($other)) {
      $arr[] = $other;
    }
    return implode(',', $arr);
  }

  /**
   * Find spatial systems for the settings form.
   *
   * Convert the stored value for indicia_spatial_systems into values to use as
   * defaults for controls on the form.
   *
   * @param array $systems
   *   The list of spatial systems to map to. Any others go into the
   *   array['other'] part of the response.
   * @param object $config
   *   Drupal configuration object for Indicia settings.
   *
   * @return array
   *   Associative array containing entries called list (an array of available
   *   systems) and other (an array of non-standard EPSG codes).
   */
  private function formValuesFromSrefSystems(array $systems, $config) {
    $r = [
      'list' => [],
      'other' => [],
    ];
    $var = explode(',', $config->get('spatial_systems'));
    foreach ($var as $sys) {
      // Check if this is one on the list, or should go in other.
      if (isset($systems[$sys])) {
        $r['list'][] = $sys;
      }
      else {
        $r['other'][] = $sys;
      }
    }
    // Implode the other systems into a comma separated list.
    $r['other'] = implode(',', $r['other']);
    return $r;
  }

}
