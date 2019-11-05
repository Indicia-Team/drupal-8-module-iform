<?php

use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\user\Entity\User;

/**
 * @file
 * Provides hostsite_* methods to decouple client_helper code from the running content
 * management system, in this case Drupal 8.
 */

/**
 * Retrieve a user field value.
 *
 * Function that allows Indicia code to hook into the Drupal user fields to
 * find out a field value, without introducing a Drupal code dependency.
 *
 * @param string $field
 *   Name of a user field to return. This can be 'language' which returns a
 *   user's 2 character language code, 'id' to return the user's ID, 'name' to
 *   return the username, or a profile field, e.g. indicia_user_id as
 *   introduced by the Easy Login feature. This will be converted to the
 *   profile field name if not prefixed with 'profile_'.
 * @param mixed $default
 *   Default value to return if not set.
 * @param bool $array
 *   Set to true if this field allows multiple values so you need an array.
 * @param int $uid
 *   User ID of user to load data for. Defaults to current user.
 *
 * @return mixed
 *   Field value.
 *
 * @todo Search client_helpers for references to global $user and uid
 */
function hostsite_get_user_field($field, $default = FALSE, $array = FALSE, $uid = FALSE) {
  global $_iform_warehouse_override;
  $account = \Drupal::currentUser();
  if ($field === 'uid' || $field === 'id') {
    return $uid === FALSE ? $account->id() : $uid;
  }
  // Do we need to load a different user account, e.g. if viewing another
  // user's details?
  $differentUser = ($uid !== FALSE && $uid !== $account->id());
  $uid = ($uid === FALSE) ? $account->id() : $uid;
  $account = User::load($uid);
  if ($_iform_warehouse_override && $field === 'indicia_user_id') {
    // If switched to a different warehouse, the default stored indicia User ID
    // will be wrong.
    return _iform_get_alternative_user_id($account, $default);
  }
  // Only load the account's language if inspecting another user, else use the
  // UI language.
  if ($field === 'language') {
    return \Drupal::languageManager()->getCurrentLanguage()->getId();
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
 *
 * @param string $field
 *   The name of a profile field.
 * @param mixed $data
 *   The data to be saved into the field.
 *
 * @return boolean
 *   Returns false if the uid or field is not available so data cannot be
 *   written.
 */
function hostsite_set_user_field($field, $data) {
  $account = \Drupal::currentUser();
  if ($account->id()) {
    $fieldname = (substr($field, 0, 6) === 'field_' ? '' : 'field_') . $field;
    $user = \Drupal\user\Entity\User::load($account->id());
    if ($user->$fieldname) {
      $user->$fieldname->setValue($data);
      $user->save();
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Returns true if the current user is permitted to perform a named task.
 * @param string $task
 * @return boolean True if allowed
 */
function hostsite_user_has_permission($task, $uid = FALSE) {
  $account = $uid ? \Drupal\user\Entity\User::load($uid) : \Drupal::currentUser();
  return $account->hasPermission($task);
}

/**
 * Display a message in a way that can be unhooked from Drupal.
 * @param string $msg Message to display
 * @param string $type Message type, one of 'status', 'warning' and 'error'.
 * @param boolean $redirect Set to true if the page is about to be redirected and the
 * message needs to be shown after redirecting, since Drupal 8's method of redirection
 * clears the messages so we need to save the message to be picked up after the redirection.
 *
 */
function hostsite_show_message($msg, $type = 'status', $redirect=false) {
  if ($redirect) {
    // If about to redirect, then save the message for later.
    if (!isset($_SESSION['iform-messages']))
      $_SESSION['iform-messages'] = [];
    $_SESSION['iform-messages'][] = array($msg, $type);
    $_SESSION['iform-redirect-from'] = hostsite_get_current_page_path();
  } else {
    drupal_set_message($msg, $type);
  }
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
 * @param string $fragment An optional named anchor to add to the URL.
 */
function hostsite_goto_page($path, $params = FALSE, $fragment=false) {
  static $iform_redirected_to;
  if ($path === $iform_redirected_to) {
    drupal_set_message("Attempt to redirect to $path caused a redirect loop");
  }
  else {
    $response = new RedirectResponse(hostsite_get_url($path, $params, $fragment));
    $response->send();
    exit();
  }
}

/**
 * Return from the current request with 403 access denied.
 */
function hostsite_access_denied() {
  throw new Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
}

/**
 * Retrieve the URL for a page identified by a path (e.g. node/1 or an alias).
 * @param string $path The page path.
 * @param array $params The params to add to the URL.
 * @param string $fragment An optional named anchor to add to the URL.
 */
function hostsite_get_url($path, $params = FALSE, $fragment=false) {
  if ($path==='<front>')
    $path = '';
  $opts = array();
  if ($params)
    $opts['query'] = $params;
  if ($fragment)
    $opts['fragment']=$fragment;
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
 *
 * @param string $context
 *   Context of the variable, either site or iform.
 * @param string $name
 *   Name of the config value to retrieve, e.g. name or mail.
 * @param mixed $default
 *   The default value to return if the config key does not exist.
 * @return mixed
 *   The config value.
 */
function hostsite_get_config_value($context, $name, $default = FALSE) {
  $configName = $context === 'site' ? 'system.site' : 'iform.settings';
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

/**
 * Returns true if a Drupal module is enabled.
 * @param string $module
 * @return boolean True if enabled
 */
function hostsite_module_exists($module) {
  return \Drupal::moduleHandler()->moduleExists($module);
}

/**
 * Encodes a string for use in a mime header.
 * @param $string
 * @return string
 */
function hostsite_mime_header_encode($string) {
  return Unicode::mimeHeaderEncode($string);
}

/**
 * Returns the config options required to drive Elasticsearch driven content.
 *
 * @param integer $nid
 *   Optional ID of node which may override site-wide Elasticsearch settings.
 *
 * @return array
 *   Settings structure.
 */
function hostsite_get_es_config($nid) {
  $config = \Drupal::config('iform.settings');
  $paramNames = [
    'version',
    'endpoint',
    'user',
    'secret',
    'warehouse_prefix',
    'all_records_permission',
    'my_records_permission',
    'location_collation_records_permission'
  ];
  $defaults = [
    'version' => '6',
  ];
  $esParams = [];
  if ($nid) {
    $nodeParams = hostsite_get_node_field_value($nid, 'params');
  }
  foreach ($paramNames as $name) {
    $esParams[$name] = $config->get("elasticsearch_$name");
    if (!$esParams[$name] === NULL && isset($defaults[$name])) {
      $esParams[$name] = $defaults[$name];
    }
    // Allow page level override.
    if ($nid) {
      $esParams[$name] = !empty($nodeParams[$name])
        ? $nodeParams[$name]
        : $esParams[$name];
    }
  }
  // Also grab some Indicia connection data.
  $paramNames = [
    'website_id',
    'password',
    'base_url',
  ];
  $indiciaParams = [];
  foreach ($paramNames as $name) {
    $indiciaParams[$name] = $config->get($name, '');
    // Allow page level override.
    if ($nid && empty($indiciaParams[$name]) || $config->get('allow_connection_override', FALSE)) {
      $indiciaParams[$name] = isset($nodeParams[$name]) ? $nodeParams[$name] : $indiciaParams[$name];
    }
  }
  return [
    'es' => $esParams,
    'indicia' => $indiciaParams,
  ];
}

/**
 *
 */
function _iform_get_alternative_user_id($account, $default) {
  global $_iform_warehouse_override;
  $alternatives = isset($account->field_alt_indicia_user_ids->value) ?
      $account->field_alt_indicia_user_ids->value : '{}';
  iform_load_helpers(array('data_entry_helper'));
  $alternatives = json_decode($alternatives, true);
  if (array_key_exists($_iform_warehouse_override['base_url'], $alternatives))
    return $alternatives[$_iform_warehouse_override['base_url']];
  else {
    // This user hasn't connected to this warehouse before. So get their user ID.
    $response = easy_login_call_get_user_id($account);
    $response = json_decode($response);
    if (isset($response->userId)) {
      $alternatives[$_iform_warehouse_override['base_url']] = $response->userId;
      $account->field_alt_indicia_user_ids->setValue(json_encode($alternatives));
      $account->save();
      return $response->userId;
    } else {
      return $default;
    }
  }
}
/**
 *
 */
function hostsite_get_cms_version()
{
  return \Drupal::VERSION;
}

/**
 *
 */
function hostsite_invoke_alter_hooks($hook, &$args)
{
  $module_handler = \Drupal::moduleHandler();
  $module_handler->loadAll();
  $module_handler->alter($hook, $args);
}
