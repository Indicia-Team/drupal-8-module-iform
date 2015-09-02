<?php

use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @file
 * Provides hostsite_* methods to decouple client_helper code from the running content
 * management system, in this case Drupal 8.
 */

/**
 * Function that allows Indicia code to hook into the Drupal user fields to find out a field value, without
 * introducing a Drupal code dependency.
 * @global type $user
 * @param string $field Name of a user field to return. This can be 'language' which returns a user's 2 character language code,
 * 'id' to return the user's ID, 'name' to return the username, or a profile field, e.g. indicia_user_id as introduced by the Easy Login feature. This will be converted
 * to the profile field name if not prefixed with 'profile_'.
 * @param mixed $default Default value to return if not set.
 * @param boolean $array Set to true if this field allows multiple values so you need an array
 * @param type $uid User ID of user to load data for. Defaults to current user.
 * @return mixed
 * @todo Search client_helpers for references to global $user and uid
 */
function hostsite_get_user_field($field, $default = FALSE, $array = FALSE, $uid = FALSE) {
  $account = \Drupal::currentUser();
  if ($field === 'uid' || $field === 'id') {
    return $uid === FALSE ? $account->id() : $uid;
  }
  // do we need to load a different user account, e.g. if viewing another user's details?
  $differentUser = ($uid !== FALSE && $uid !== $account->id());
  $uid = ($uid === FALSE) ? $account->id() : $uid;
  $account = \Drupal\user\Entity\User::load($uid);
  // Only load the account's language if inspecting another user, else use the UI language.
  if ($field === 'language') {
    // @todo Test loading user's language under i18n environment
    if (isset($account->language) && $differentUser) {
      return $account->language->getId();
    }
    else {
      global $language;
      return $language->getId();
    }
  }
  elseif ($field === 'name') {
    return $account->getUsername();
  }
  elseif ($field === 'mail') {
    return $account->getEmail();
  }
  $fieldname = (substr($field, 0, 6) === 'field_' ? '' : 'field_') . $field;
  if (isset($account->$fieldname)) {
    // @todo Test what happens if a multi-value field
    return $account->$fieldname->value;
  }
  else {
    return $default;
  }
}

/**
 * Function that allows Indicia code to hook into the Drupal user profile to set a field value, without
 * introducing a Drupal code dependency.
 * @global type $user
 * @param type $field The name of a profile field.
 * @param type $data The data to be saved into the field.
 * @return boolean Returns false if the uid or field is not available so data cannot be written
 *
 * function hostsite_set_user_field($field, $data) {
 * global $user;
 * if ($user->uid) {
 * $fieldname = (substr($field, 0, 6)==='field_' ? '' : 'field_') . $field;
 * $account = user_load($user->uid);
 * if (!isset($account->$fieldname))
 * return FALSE;
 * $field = $account->$fieldname;
 * $language = $account->language ? $account->language : 'und';
 * if (isset($field[$language]))
 * $field[$language][0]['value'] = $data;
 * else
 * $field[$language]=array(array('value' => $data));
 * $account->$fieldname = $field;
 * $info = field_info_field($fieldname);
 * $fields = array($info['id']);
 *
 * // Execute the storage function
 * field_sql_storage_field_storage_write('user', $account, 'update', $fields);
 *
 * // Clear field cache
 * cache_clear_all("field:user:$account->uid", 'cache_field');
 * return TRUE;
 * }
 * return FALSE;
 * }
 *
 * /**
 * Enable a library in rhw host website.
 * @param string $library Name of the library. The following are supported:
 *
 * collapse - To enable collapsible fieldsets. Requires the class collapsible to be added to a fieldset.
 * jquery-ui - Adds the jQuery ui library with tab and datepicker support.
 *
 * function hostsite_add_library($library) {
 * switch ($library) {
 * case 'collapse':
 * drupal_add_library('system', 'drupal.form');
 * drupal_add_library('system', 'drupal.collapse');
 * break;
 * case 'jquery-ui':
 * drupal_add_library('system', 'jquery-ui');
 * drupal_add_library('system', 'ui.datepicker');
 * drupal_add_library('system', 'ui.tabs');
 * drupal_add_library('system', 'ui.progressbar');
 * drupal_add_library('system', 'ui.sortable');
 * drupal_add_library('system', 'ui.dialog');
 * drupal_add_library('system', 'effects.fade');
 * drupal_add_library('system', 'effects.slide');
 * break;
 * default:
 * \Drupal::logger('iform')->notice("Unknown library requested: $library");
 * }
 * }
 *
 * /**
 * Display a message in a way that can be unhooked from Drupal.
 * @param string $msg Message to display
 * @param string $type Message type, one of 'status', 'warning' and 'error'.
 */
function hostsite_show_message($msg, $type = 'status') {
  drupal_set_message($msg, $type);
}

/**
 * Set a breadcrumb trail in the host site.
 * @param array $breadcrumb An associative array where keys are untranslated breadcrumb
 * terms and values are the page to go to. The home page is automatically inserted at the
 * start and the current page at the end.
 *
 * function hostsite_set_breadcrumb($breadcrumb) {
 * // convert to Drupal style breadcrumb array
 * $drupal_breadcrumb = array(l(lang::get('Home'),'<front>'));
 * foreach ($breadcrumb as $label => $path) {
 * $drupal_breadcrumb[] = l(lang::get($label), $path);
 * }
 * // add current page
 * $drupal_breadcrumb[] = drupal_get_title();
 * drupal_set_breadcrumb($drupal_breadcrumb);
 * }
 *
 * /**
 * Set the page title in the host site.
 * @param string $title The translated page title.
 */
function hostsite_set_page_title($title) {
  global $indicia_page_title;
  $indicia_page_title = $title;
}

/**
 * Retrieves the current node page's title
 * @param $nid Node ID
 * @return string
 */
function hostsite_get_page_title($nid) {
  static $indicia_page_title;
  if (isset($indicia_page_title)) {
    return $indicia_page_title;
  }
  $node = \Drupal\node\Entity\Node::load($nid);
  return $node->getTitle();
}

/**
 * Get's a field value for the current node.
 * @param integer $nid The Node ID
 * @param string $field The field name
 * @return mixed The field value
 */
function hostsite_get_node_field_value($nid, $field) {
  $node = \Drupal\node\Entity\Node::load($nid);
  // @todo Test, document, copy to D6 & 7
  if ($field === 'params') {
    return $node->params;
  }
  else {
    if ($field !== 'title' && $field !== 'body' && !preg_match('/^field_/', $field)) {
      $field = "field_$field";
    }
    // @todo Do we need this test?
    if (!isset($node->$field)) {
      drupal_set_message("Could not find $field");
      return FALSE;
    }

    return $node->$field->value;
  }
}

/**
 * Goto a page on the host site.
 * @param string $path The page path.
 * @param array $params The params to add to the URL.
 */
function hostsite_goto_page($path, $params = FALSE) {
  static $iform_redirected_to;
  if ($path === $iform_redirected_to) {
    drupal_set_message("Attempt to redirect to $path caused a redirect loop");
  }
  else {
    $response = new RedirectResponse(hostsite_get_url($path, $params));
    $response->send();
    return;
  }
}

/**
 * Retrieve the URL for a page identified by a path (e.g. node/1 or an alias).
 * @param string $path The page path.
 * @param array $params The params to add to the URL.
 */
function hostsite_get_url($path, $params = FALSE) {
  if ($path==='<front>')
    $path = '';
  $opts = array();
  if ($params) {
    $opts['query'] = $params;
  }
  return \Drupal\Core\Url::fromUri("base:$path", $opts)->toString();
}

/**
 * Retrieves the path for the current page.
 * @return string The current page path.
 */
function hostsite_get_current_page_path() {
  $path = \Drupal::service('path.current')->getPath();
  $alias = \Drupal::service('path.alias_manager')->getAliasByPath($path);
  return trim($alias, '/');
}

/**
 * Retrieve a configuration value, e.g. site name or site mail, or an indicia setting.
 * @param string $context Context of the variable, either site or iform.
 * @param string $name Name of the config value to retrieve, e.g. name or mail.
 * @param mixed $default The default value to return if the config key does not exist.
 * @return mixed The config value.
 */
function hostsite_get_config_value($context, $name, $default = FALSE) {
  $configName = $context === 'site' ? 'system.site' : 'indicia.settings';
  $r = \Drupal::config($configName)->get($name);
  return $r === NULL ? $default : $r;
}

/**
 * Retrieves a path suitable for storing public files (the Drupal public file path).
 * @return string The path to the location for public file storage
 */
function hostsite_get_public_file_path() {
  return PublicStream::basePath();
}


/**
 * Returns true if there is a form on this site for editing groups. This allows group
 * related functionality to be turned on and off as appropriate.
 * @return boolean True if group edit pages exist on this site.
 * @todo Port back to Drupal 6 and 7 and commit changes to form_helper which call this.
 */
function hostsite_has_group_functionality() {
  $query = \Drupal::entityQuery('node')
    ->condition('type', 'iform_page')
    ->condition('field_iform', 'group_edit');
  $nids = $query->execute();
  return count($nids) > 0;
}

/**
 * Retrieve the available pages which can be linked to a recording group and will
 * therefore be filtered to the content of that group.
 * @param integer $group_id ID of the group to load available pages for
 * @return array Array of pages titles keyed by node path.
 */
function hostsite_get_group_compatible_pages($group_id) {
  $query = \Drupal::entityQuery('node')
    ->condition('type', 'iform_page')
    ->condition('field_available_for_groups', '1');
  $node_storage = \Drupal::entityManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($query->execute());
  $r = array();
  foreach ($nodes as $node) {
    // some group pages are limited to a single group
    if ($node->field_limit_to_group_id->value === null || $node->field_limit_to_group_id->value === $group_id) {
      $alias = \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$node->id());
      $r[trim($alias, '/')] = $node->title->value;
    }
  }
  return $r;
}