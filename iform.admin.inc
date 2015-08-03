<?php
/**
* @file
* Include file to define the admin interface for the iform module.
*/

/** 
 * A form definition for providing global configuration options for Indicia. Used both in the installation
 * profile as an install task, and provided on the admin menu.
 */
function iform_configuration_form($form, &$form_state, $url='', $submit_handler='') {
  // @todo Copy this into SettingsForm controller.
  // Add any module declared additional iform variables to the settings form. Extra variables only 
  // visible after install, as they may need a website connection
  if ((!defined('MAINTENANCE_MODE') || MAINTENANCE_MODE != 'install')  && variable_get('indicia_website_id', false)) {
    $readAuth = data_entry_helper::get_read_auth(variable_get('indicia_website_id', 0), variable_get('indicia_password', ''));
    // Add any indicia variables declared by hook_variable_info
    global $language;
    $vars = module_invoke_all('variable_info', array("language" => $language));
    $ctrls = array();
    foreach ($vars as $var => $config) {
      $matches = array();
      if (isset($config['addToIndiciaSettingsPage']) && $config['addToIndiciaSettingsPage']) {
        if (isset($config['subtype']) && preg_match('/^termlists_term:(?P<list>.+)/', $config['subtype'], $matches))
          $type = 'select';  
        elseif (isset($config['subtype']) && preg_match('/^(?P<table>[a-z_]+)\.(?P<caption>[a-z_]+)/', $config['subtype'], $matches))
          $type = 'select';  
        else
          $type = ($config['type'] == 'radios' ? 'radios' : 'textfield');
        $ctrls[$var] = array(
          '#type'=>$type,
          '#title'=>$config['title'],
          '#description'=>$config['description'],
          '#default_value'=>variable_get($var, isset($config['default']) ? $config['default'] : '')
        );
        if (isset($config['options'])) $ctrls[$var]['#options'] = $config['options'];
        try {
          // output buffer used to catch and discard errors as we'll handle them ourselves and don't want them on screen.
          ob_start();
          if (isset($matches['list']) || isset($matches['table'])) {
            if (isset($matches['list'])) {
              $rows = data_entry_helper::get_population_data(array(
                'table' => 'termlists_term',
                'extraParams' => $readAuth + array('termlist_external_key' => 'indicia:' . $matches['list'])
              ));
              $captionField='term';
            }
            else {
              $rows = data_entry_helper::get_population_data(array(
                'table' => $matches['table'],
                'extraParams' => $readAuth,
                'columns' => 'id,'.$matches['caption']
              ));
              $captionField=$matches['caption'];
            }
            $options = array('' => t('<please select>'));
            foreach ($rows as $row) {
              $options[$row['id']] = $row[$captionField];
            }
            $ctrls[$var]['#options'] = $options;
          }
          ob_end_clean();
        } catch (Exception $e) {
          ob_end_clean();
          watchdog('iform', 'Error loading iform settings for '.$config['title'].': '.$e->getMessage());
          drupal_set_message("Unable to load the control $config[title], probably because the warehouse connection is not correctly configured. ");
          unset($ctrls[$var]);
        }
      }
    }
    if (!empty($ctrls)) {
      $form['additional'] = array_merge(array(
        '#type' => 'fieldset',
        '#title' => t('Additional Settings')
      ), $ctrls);
    }
  }
}




