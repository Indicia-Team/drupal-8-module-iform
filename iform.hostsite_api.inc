<?php

/**
 * @file
 * Shim to provide version independent access to Drupal functionality.
 *
 * Provides hostsite_* methods to decouple client_helper code from the running
 * content management system, in this case Drupal 8.
 */

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormState;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
 *   Set to TRUE if this field allows multiple values so you need an array.
 * @param int $uid
 *   Drupal user ID of user to load data for. Defaults to current user.
 *
 * @return mixed
 *   Field value.
 *
 * @todo Search client_helpers for references to global $user and uid
 */
function hostsite_get_user_field($field, $default = FALSE, $array = FALSE, $uid = FALSE) {
  global $_iform_warehouse_override;
  if ($_iform_warehouse_override && $field === 'indicia_user_id') {
    return $default;
  }
  if ($uid && $field !== 'id' && $field !== 'uid') {
    // Load another user's account - not necessary if just requesting the ID.
    $account = User::load($uid);
    // Only load the account's language if inspecting another user.
    if ($field === 'language') {
      return $account->getPreferredLangcode();
    }
  }
  elseif (!$uid) {
    $account = \Drupal::currentUser();
    $uid = $account->id();
    if ($field === 'language') {
      // For current user, use the UIs language.
      return \Drupal::languageManager()->getCurrentLanguage()->getId();
    }
  }
  if ($field === 'uid' || $field === 'id') {
    return $uid;
  }
  // Only load the account's language if inspecting another user, else use the
  // UI language.
  if ($field === 'name') {
    return $account->getDisplayName();
  }
  elseif ($field === 'mail') {
    return $account->getEmail();
  }
  // Need fully loaded user account to access fields.
  $user = User::load($account->id());
  $fieldname = (substr($field, 0, 6) === 'field_' ? '' : 'field_') . $field;
  if (isset($user->$fieldname)) {
    if ($user->$fieldname->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() !== 1) {
      // Not a single value field, so return array or serialized list.
      $values = [];
      $count = count($user->$fieldname);
      for ($i = 0; $i < $count; $i++) {
        $values[] = $user->$fieldname[$i]->value;
      }
      return $array ? $values : serialize($values);
    }
    else {
      return $user->$fieldname->value;
    }
  }
  else {
    return $default;
  }
}

/**
 * Finds a Drupal user by a field value.
 *
 * E.g. search by indicia_user_id.
 *
 * @param string $field
 *   Name of the field to search.
 * @param string $value
 *   Value to search for.
 *
 * @return array
 *   List of matching user IDs.
 */
function hostsite_find_cms_user_by_field_value($field, $value) {
  $fieldname = (substr($field, 0, 6) === 'field_' ? '' : 'field_') . $field;
  $query = \Drupal::entityQuery('user')->accessCheck(FALSE);
  $query->condition($fieldname, $value);
  return array_keys($query->execute());
}

/**
 * Set a user field value.
 *
 * Function that allows Indicia code to hook into the Drupal user profile to
 * set a field value, without introducing a Drupal code dependency.
 *
 * @param string $field
 *   The name of a profile field.
 * @param mixed $data
 *   The data to be saved into the field.
 *
 * @return bool
 *   Returns FALSE if the uid or field is not available so data cannot be
 *   written.
 */
function hostsite_set_user_field($field, $data) {
  $account = \Drupal::currentUser();
  if ($account->id()) {
    $fieldname = (substr($field, 0, 6) === 'field_' ? '' : 'field_') . $field;
    $user = User::load($account->id());
    if (isset($user->$fieldname) && $user->$fieldname) {
      $user->$fieldname->setValue($data);
      $user->save();
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Returns TRUE if the current user is permitted to perform a named task.
 *
 * @param string $task
 *   Permission name.
 *
 * @return bool
 *   True if allowed.
 */
function hostsite_user_has_permission($task, $uid = FALSE) {
  $account = $uid ? User::load($uid) : \Drupal::currentUser();
  return $account->hasPermission($task);
}

/**
 * Display a message in a way that can be unhooked from Drupal.
 *
 * @param string $msg
 *   Message to display.
 * @param string $type
 *   Message type, one of 'status', 'warning' and 'error'.
 * @param bool $redirect
 *   Set to TRUE if the page is about to be redirected and the message needs to
 *   be shown after redirecting, since Drupal 8's method of redirection clears
 *   the messages so we need to save the message to be picked up after the
 *   redirection.
 */
function hostsite_show_message($msg, $type = 'status', $redirect = FALSE) {
  if ($redirect) {
    // If about to redirect, then save the message for later.
    if (!isset($_SESSION['iform-messages'])) {
      $_SESSION['iform-messages'] = [];
    }
    $_SESSION['iform-messages'][] = [$msg, $type];
    $_SESSION['iform-redirect-from'] = hostsite_get_current_page_path();
  }
  else {
    \Drupal::messenger()->addMessage($msg, $type);
  }
}

/**
 * Set a breadcrumb trail in the host site.
 *
 * @param array $breadcrumb
 *   An associative array where keys are page paths and values are untranslated
 *   breadcrumb terms. The home page is automatically inserted at the start and
 *   the current page at the end.
 * @param bool $includeCurrentPage
 *   Set to false to prevent addition of the current page as a label at the end
 *   of the breadcrumb.
 * @param bool $force
 *   By default, breadcrumbs are only set on iform node pages. If using a block
 *   or some form of node list (e.g. a view) then hostsite_set_breadcrumb will
 *   do nothing unless the $force parameter is set to true.
 */
function hostsite_set_breadcrumb(array $breadcrumb, $includeCurrentPage = TRUE, $force = FALSE) {
  if ($includeCurrentPage) {
    // CurrentPageTitle will be replaced later in case it gets updated by
    // code.
    $breadcrumb['<none>'] = '#currentPageTitle#';
  }
  helper_base::$breadcrumb = $breadcrumb;
  helper_base::$force_breadcrumb = $force;
}

/**
 * Set the page title in the host site.
 *
 * @param string $title
 *   The translated page title.
 */
function hostsite_set_page_title($title) {
  global $_iform_page_title;
  $_iform_page_title = $title;
}

/**
 * Retrieves the current node page's title.
 *
 * @param int $nid
 *   Node ID.
 *
 * @return string
 *   Page title.
 */
function hostsite_get_page_title($nid) {
  global $_iform_page_title;
  if (isset($_iform_page_title)) {
    return $_iform_page_title;
  }
  $node = Node::load($nid);
  return $node->getTitle();
}

/**
 * Get's a field value for the current node.
 *
 * @param int $nid
 *   The Node ID.
 * @param string $field
 *   The field name.
 *
 * @return mixed
 *   The field value.
 */
function hostsite_get_node_field_value($nid, $field) {
  $node = Node::load($nid);
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
      \Drupal::messenger()->addMessage("Could not find $field");
      return FALSE;
    }

    return $node->$field->value;
  }
}

/**
 * Goto a page on the host site.
 *
 * Do not call this from within a hook because it can prevent other hooks
 * from executing with unpredictable results.
 *
 * A seemingly safe way to redirect in all cases is to set the 'destination'
 * request parameter. E.g.
 *     $url = hostsite_get_url($path, $params, $fragment);
 *     $request = \Drupal::request();
 *     $request->query->set('destination', $url);
 *
 * I have not implemented the above here because it requires a restructure of
 * iform/src/Controller/IformController.php::joinGroupCallback() so that the
 * function returns a value even when there is a redirect.
 *
 * @param string $path
 *   The page path.
 * @param array $params
 *   The params to add to the URL.
 * @param string $fragment
 *   An optional named anchor to add to the URL, excluding the # character.
 */
function hostsite_goto_page($path, array $params = [], $fragment = FALSE) {
  static $iform_redirected_to;
  if ($path === $iform_redirected_to) {
    \Drupal::messenger()->addMessage("Attempt to redirect to $path caused a redirect loop");
  }
  else {
    // See https://www.drupal.org/node/2023537 for
    // Redirecting when not in context of a controller.
    $response = new RedirectResponse(hostsite_get_url($path, $params, $fragment));
    \Drupal::service('http_middleware.iform_redirect')->setRedirectResponse($response);
  }
}

/**
 * Return from the current request with 403 access denied.
 */
function hostsite_access_denied() {
  throw new AccessDeniedHttpException();
}

/**
 * Retrieve the URL for a page identified by a path (e.g. node/1 or an alias).
 *
 * @param string $path
 *   The page path.
 * @param array $params
 *   The params to add to the URL.
 * @param string $fragment
 *   An optional named anchor to add to the URL, excluding the # character.
 * @param bool $absolute
 *   Set to TRUE for an absolute URL.
 *
 * @return string
 *   Url as string.
 */
function hostsite_get_url($path, array $params = [], $fragment = FALSE, $absolute = FALSE) {
  if ($path === '<front>') {
    $path = '';
  }
  $opts = ['absolute' => $absolute];
  if (!empty($params)) {
    $opts['query'] = $params;
  }
  if (!empty($fragment)) {
    $opts['fragment'] = $fragment;
  }
  return Url::fromUri("base:$path", $opts)->toString();
}

/**
 * Retrieve the path alias for a node.
 *
 * @param int $nid
 *   Node ID.
 *
 * @return string
 *   Path alias string.
 */
function hostsite_get_alias($nid) {
  return trim(\Drupal::service('path_alias.manager')->getAliasByPath("/node/$nid"), '/');
}

/**
 * Retrieves the path for the current page.
 *
 * @return string
 *   The current page path.
 */
function hostsite_get_current_page_path() {
  $path = \Drupal::service('path.current')->getPath();
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);
  return trim($alias, '/');
}

/**
 * Retrieve a configuration value.
 *
 * For example, the site name or site mail, or an indicia setting.
 *
 * @param string $context
 *   Context of the variable, either site or iform.
 * @param string $name
 *   Name of the config value to retrieve, e.g. name or mail.
 * @param mixed $default
 *   The default value to return if the config key does not exist.
 *
 * @return mixed
 *   The config value.
 */
function hostsite_get_config_value($context, $name, $default = FALSE) {
  $configName = $context === 'site' ? 'system.site' : 'iform.settings';
  $r = \Drupal::config($configName)->get($name);
  return $r ?? $default;
}

/**
 * Retrieves the public file path.
 *
 * @return string
 *   The path to the location for public file storage
 */
function hostsite_get_public_file_path() {
  return PublicStream::basePath();
}

/**
 * Returns TRUE if there is a form on this site for editing groups.
 *
 * This allows group related functionality to be turned on and off as
 * appropriate.
 *
 * @return bool
 *   True if group edit pages exist on this site.
 */
function hostsite_has_group_functionality() {
  $query = \Drupal::entityQuery('node')
    ->condition('type', 'iform_page')
    ->condition('field_iform', 'group_edit')
    ->accessCheck(FALSE);
  $nids = $query->execute();
  return count($nids) > 0;
}

/**
 * Retrieve the available pages which can be linked to a recording group.
 *
 * These pages will therefore be filtered to the content of that group.
 *
 * @param int $group_id
 *   ID of the group to load available pages for.
 *
 * @return array
 *   Array of pages titles keyed by node path.
 */
function hostsite_get_group_compatible_pages($group_id) {
  $r = [];
  $query = \Drupal::entityQuery('node')
    ->condition('field_available_for_groups', '1')
    ->accessCheck(FALSE);
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($query->execute());
  foreach ($nodes as $node) {
    // Some group pages are limited to a single group.
    if (!isset($node->field_limit_to_group_id) || $node->field_limit_to_group_id->value === NULL || $node->field_limit_to_group_id->value === $group_id) {
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id());
      $r[trim($alias, '/')] = $node->title->value;
    }
  }
  return $r;
}

/**
 * Returns TRUE if a Drupal module is enabled.
 *
 * @param string $module
 *   Module name.
 *
 * @return bool
 *   True if enabled
 */
function hostsite_module_exists($module) {
  return \Drupal::moduleHandler()->moduleExists($module);
}

/**
 * Encodes a string for use in a mime header.
 *
 * @param string $string
 *   String to encode.
 *
 * @return string
 *   Encoded string.
 */
function hostsite_mime_header_encode($string) {
  return Unicode::mimeHeaderEncode($string);
}

/**
 * Returns the config options required to drive Elasticsearch driven content.
 *
 * @param int $nid
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
    'alternative_endpoints',
    'auth_method',
    'scope',
    'user',
    'secret',
    'warehouse_prefix',
    'all_records_permission',
    'my_records_permission',
    'location_collation_records_permission',
  ];
  $defaults = [
    'version' => '6',
    'auth_method' => 'directClient',
    'scope' => 'reporting',
  ];
  $esParams = [];
  if ($nid) {
    $nodeParams = hostsite_get_node_field_value($nid, 'params');
  }
  foreach ($paramNames as $name) {
    $esParams[$name] = $config->get("elasticsearch_$name");
    if ($esParams[$name] === NULL && isset($defaults[$name])) {
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
    $indiciaParams[$name] = $config->get($name);
    // Allow page level override.
    if ($nid && empty($indiciaParams[$name]) || $config->get('allow_connection_override')) {
      $indiciaParams[$name] = $nodeParams[$name] ?? $indiciaParams[$name];
    }
  }
  return [
    'es' => $esParams,
    'indicia' => $indiciaParams,
  ];
}

/**
 * Retrieve the current Drupal version.
 *
 * @return string
 *   The Drupal version value.
 */
function hostsite_get_cms_version() {
  return \Drupal::VERSION;
}

/**
 * Invoke a hook in each Drupal module, allowing modification of a variable.
 *
 * @param string $hook
 *   Name of the hook to invoke in each enabled Drupal module.
 * @param mixed $args
 *   Reference to an argument to be passed to the hook. This is read/write, and
 *   contents may be altered by the hook.
 */
function hostsite_invoke_alter_hooks($hook, &$args) {
  $module_handler = \Drupal::moduleHandler();
  $module_handler->loadAll();
  $module_handler->alter($hook, $args);
}

/**
 * Renders a Drupal form.
 *
 * @param string $formId
 *   Form identifier.
 * @param array $state
 *   Form state key/value pairs.
 *
 * @return string
 *   Form HTML.
 */
function hostsite_render_form($formId, array $state) {
  // Auto map from D7 to D8 form IDs. Additional forms should be added as
  // required.
  $mappings = [
    'user_login' => '\Drupal\user\Form\UserLoginForm',
  ];
  if (isset($mappings[$formId])) {
    $formId = $mappings[$formId];
  }
  $form_state = new FormState();
  // Map from D7 form state to D8 functions.
  if (!empty($state['noredirect'])) {
    $form_state->disableRedirect();
    unset($state['noredirect']);
  }
  foreach ($state as $key => $value) {
    $form_state->set($key, $value);
  }
  return \Drupal::service('renderer')->render(\Drupal::formBuilder()->buildForm($formId, $form_state));
}

/**
 * Set a non-essential cookie.
 *
 * Respects settings in EU Cookie Compliance module.
 *
 * @param string $cookie
 *   Cookie name.
 * @param string $value
 *   Cookie value.
 * @param int $expire
 *   Optional expiry value.
 */
function hostsite_set_cookie($cookie, $value, $expire = 0) {
  // Respect the remembered_fields_optin control.
  if (isset($_POST['cookie_optin']) && $_POST['cookie_optin'] === '0') {
    return;
  }
  // Respect the EU Cookie Compliance module.
  if (\Drupal::service('module_handler')->moduleExists('eu_cookie_compliance')) {
    if (!isset($_COOKIE['cookie-agreed']) || $_COOKIE['cookie-agreed'] === '0') {
      return;
    }
  }
  setcookie($cookie, $value, $expire);
  // Cookies are only set when the page is loaded. So, fudge the cookie array.
  $_COOKIE[$cookie] = $value;
}

/**
 * Gets a non-essential cookie.
 *
 * Adds optional character string prefix to cookie name.
 *
 * @param string $cookie
 *   Cookie name.
 * @param string $default
 *   Default to return if not available.
 *
 * @return string
 *   Cookie value.
 */
function hostsite_get_cookie($cookie, $default = FALSE) {
  $config = \Drupal::config('iform.settings');
  $cookie = $config->get('cookie_prefix', '') . $cookie;
  return (isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie] : $default);
}

/**
 * Returns a list of custom prebuilt forms.
 *
 * The forms can be provided by the iform_custom_forms module.
 *
 * @return array
 *   An array of file names.
 */
function hostsite_get_iform_custom_forms() {
  $forms = [];
  if (\Drupal::moduleHandler()->moduleExists('iform_custom_forms')) {
    $iformCustomFormsList = \Drupal::service('iform_custom_forms.list');
    $customForms = $iformCustomFormsList->getCustomisations()['.'];

    foreach ($customForms as $fileName => $relPath) {
      $forms[] = $fileName;
    }
  }
  return $forms;
}

/**
 * Returns the ajax path to call to obtain parameters of a form.
 */
function hostsite_get_iform_custom_forms_ajax_path() {
  if (\Drupal::moduleHandler()->moduleExists('iform_custom_forms')) {
    // Use the handler provided by the iform_custom_forms module. This does
    // come in the Drupal front door so we can call the services of the
    // module to obtain details of the forms it offers for inclusion in the
    // Ajax response.
    $path = Url::fromRoute('iform_custom_forms.ajaxParams')->toString();
  }
  else {
    // Use the handler provided by the iform module. Because this doesn't
    // come in the Drupal front door there is no Drupal context available
    // when building the Ajax response.
    $path = Url::fromRoute('<front>')->toString() . iform_client_helpers_path() . 'prebuilt_forms_ajax.php';
  }
  return $path;
}

/**
 * Enable autoloading of custom prebuilt forms.
 *
 * The forms can be provided by the iform_custom_forms module.
 */
function hostsite_autoload_iform_custom_forms() {
  if (\Drupal::moduleHandler()->moduleExists('iform_custom_forms')) {
    $iformCustomFormsList = \Drupal::service('iform_custom_forms.list');
  }
}

/**
 * Send an email.
 *
 * Either uses PHP mail(), or if the Drupal Symfony Mailer module is installed,
 * defers mail sending to that module. Ensure that a Mail Policy is set up for
 * the 'Iform emails' type at /admin/config/system/mailer.
 *
 * @param string $to
 *   Email address to send to.
 * @param [type] $subject
 *   Email subject.
 * @param [type] $body
 *   Email body (can include HTML).
 * @param array $options
 *   Additional options. Currently supports
 *   * from (defaults to the site email)
 *   * fromName (defaults to the site name)
 *   * replyTo (defaults to the user's email).
 *
 * @return bool
 *   TRUE if the email was accepted for sending (this does not mean that the
 *   email actually sent).
 */
function hostsite_send_email($to, $subject, $body, array $options = []) {
  if (\Drupal::hasService('email_factory')) {
    $email_factory = Drupal::service('email_factory');
    $email = $email_factory->newTypedEmail('iform_basic_email', 'empty', $to, $subject, $body, $options);
    if (!$email->send()) {
      $error = $email->getError();
      \Drupal::logger('iform')->error('Error sending email: ' . $error);
      return FALSE;
    }
    return TRUE;
  }
  else {
    // Revert to PHP mail().
    $siteName = hostsite_get_config_value('site', 'name', '');
    $siteEmail = hostsite_get_config_value('site', 'mail', '');
    $options = array_merge([
      'from' => $siteEmail,
      'fromName' => $siteName,
      'replyTo' => hostsite_get_user_field('mail'),
    ], $options);
    $headers = [
      'MIME-Version: 1.0',
      'Content-type: text/html; charset=UTF-8;',
      "From: \"$options[siteName]\" <$options[siteEmail]",
      "Reply-To: \"$options[replyTo]\" <$options[replyTo]>",
      'Date: ' . date(DateTime::RFC2822),
      'Message-ID: <' . time() . '-' . md5($options['fromName'] . $_POST['to']) . '@' . $_SERVER['SERVER_NAME'] . '>',
    ];
    $r = mail($to, $subject, $body, $headers) ? TRUE : FALSE;
    if (!$r) {
      \Drupal::logger('iform')->error('Attempt to send email using PHP mail() failed');
    }
    return $r;
  }
}
