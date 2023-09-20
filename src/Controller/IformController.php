<?php

namespace Drupal\iform\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;

class IformController extends ControllerBase {

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Dependency inject services.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      // Load the service required to construct this class.
      $container->get('messenger')
    );
  }

  /**
   * Constructor for dependency injection.
   */
  public function __construct($messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Prebuilt form ajax function callback.
   *
   * An Ajax callback for prebuilt forms, routed from iform/ajax.
   * Lets prebuilt forms expose a method called ajax_* which is then
   * available on a path iform/ajax/* for AJAX requests from the page.
   *
   * @param string $form
   *   The filename of the form, excluding .php.
   * @param string $method
   *   The method name, excluding the ajax_ prefix.
   * @param int $nid
   *   Node ID, used to get correct website and password. If not passed, then
   *   looks to use the globally set website Id and password.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Ajax response.
   */
  public function ajaxCallback($form, $method, $nid) {
    if ($form === NULL || $method === NULL || $nid === NULL) {
      return $this->t('Incorrect AJAX call');
    }
    $class = "\iform_$form";
    $method = "ajax_$method";

    // Require the file containing the code for the $iform.
    if (\Drupal::moduleHandler()->moduleExists('iform_custom_forms')) {
      // Enable autoloader for classes in iform_custom_forms module.
      \Drupal::service('iform_custom_forms.list');
    }
    if (!class_exists($class)) {
      require_once \iform_client_helpers_path() . 'prebuilt_forms/' . $form . '.php';
    }

    $config = \Drupal::config('iform.settings');
    $node = Node::load($nid);
    if ($node->field_iform->value !== $form) {
      hostsite_access_denied();
    }
    if ($node->params['view_access_control'] === '1') {
      $permission = empty($node->params['permission_name']) ? "access iform $nid" : $node->params['permission_name'];
      if (!hostsite_user_has_permission($permission)) {
        hostsite_access_denied();
      }
    }
    $website_id = $node->params['website_id'];
    $password = $node->params['password'];
    if (isset($node->params['base_url']) && $node->params['base_url'] !== $config->get('base_url') && $config->get('allow_connection_override')) {
      global $_iform_warehouse_override;
      $_iform_warehouse_override = [
        'base_url' => $node->params['base_url'],
        'website_id' => $website_id,
        'password' => $password,
      ];
      $path = iform_client_helpers_path();
      require_once $path . 'helper_base.php';
      \helper_base::$base_url = $node->params['base_url'];
    }
    // If node not supplied, or does not have its own website Id and password, use the
    // global drupal vars from the settings form.
    if (empty($website_id) || empty($password)) {
      $website_id = $config->get('website_id');
      $password = $config->get('password');
    }
    call_user_func([$class, $method], $website_id, $password, $nid);
    // @todo How does the echoed response actually get to the client?
    return new Response('');
  }

  /**
   * A callback for Elasticsearch proxying.
   *
   * @param string $method
   *   Name of the proxy method (e.g. searchbyparams, rawsearch, download).
   * @param int $nid
   *   Optional node ID if site wide ES configuration to be overridden.
   *
   * @return object
   *   Drupal response.
   */
  public function esproxyCallback($method, $nid = NULL) {
    require_once \iform_client_helpers_path() . 'ElasticsearchProxyHelper.php';
    try {
      \ElasticSearchProxyHelper::callMethod($method, $nid);
    }
    catch (ElasticSearchProxyAbort $e) {
      // Nothing to do.
    }
    return new Response('', http_response_code(), ['Content-type' => 'application/json']);
  }

  /**
   * A callback for Dynamic attribute retrieval proxying.
   *
   * @param string $method
   *   Name of the proxy method (e.g. searchbyparams, rawsearch, download).
   *
   * @return object
   *   Drupal response.
   */
  public function dynamicattrsproxyCallback($method) {
    require_once \iform_client_helpers_path() . 'DynamicAttrsProxyHelper.php';
    \DynamicAttrsProxyHelper::callMethod($method);
    return new Response('', http_response_code());
  }

  /**
   * Callback for shared group join links.
   *
   * @param string $title
   *   URL formatted name of the group.
   * @param string $parentTitle
   *   URL formatted name of the parent group, or NULL if no parent.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   */
  private function joinGroupCallback($title, $parentTitle, Request $request) {
    iform_load_helpers(['report_helper', 'data_entry_helper']);
    $config = \Drupal::config('iform.settings');
    $auth = \report_helper::get_read_write_auth($config->get('website_id'), $config->get('password'));
    $uid = hostsite_get_user_field('id', 0);
    $indiciaUserId = hostsite_get_user_field('indicia_user_id', 0);
    $params = [
      'title' => $title,
      'currentUser' => $indiciaUserId,
    ];
    if ($parentTitle) {
      $params['parent_title'] = $parentTitle;
    }
    // Look up the group.
    $groups = \report_helper::get_report_data([
      'dataSource' => 'library/groups/find_group_by_url',
      'readAuth' => $auth['read'],
      'extraParams' => $params,
    ]);
    if (isset($groups['error'])) {
      $this->messenger->addWarning($this->t('An error occurred when trying to access the group.'));
      \Drupal::logger('iform')->notice('Groups page load error: ' . var_export($groups, TRUE));
      hostsite_goto_page('<front>');
      return;
    }
    if (!count($groups)) {
      $this->messenger->addWarning($this->t('The group you are trying to join does not appear to exist.'));
      throw new NotFoundHttpException();
    }
    if (count($groups) > 1) {
      $this->messenger->addWarning($this->t('The group you are trying to join has a duplicate name with another group so cannot be joined in this way.'));
      hostsite_goto_page('<front>');
      return;
    }
    $group = $groups[0];
    if ($group['member'] === 't') {
      $this->messenger->addMessage($this->t("Welcome back to the @group.", ['@group' => $this->readableGroupTitle($group)]));
      return [
        '#markup' => $this->showGroupPage($group, $config->get('website_id'), $auth['read']),
      ];
    }
    elseif ($group['joining_method_raw'] === 'I') {
      $this->messenger->addWarning($this->t('The group you are trying to join is private.'));
      hostsite_goto_page('<front>');
      return;
    }
    if ($uid) {
      $r = '';
      // User is logged in.
      if (!$indiciaUserId) {
        $this->messenger->addMessage($this->t('Before joining @group, please set your surname on your user account profile.', ['@group' => $this->readableGroupTitle($group)]));
        hostsite_goto_page('<front>');
        return;
      }
      elseif ($group['pending'] === 't' && $group['joining_method'] !== 'P') {
        // Membership exists but is pending.
        $this->messenger->addMessage($this->t('Your application to join @group is still waiting for a group administrator to approve it.', ['@group' => $this->readableGroupTitle($group)]));
      }
      elseif (empty($request->query->get('confirmed'))) {
        $r .= $this->groupConfirmForm($group);
      }
      elseif (!$this->joinPublicGroup($group, $auth['write_tokens'], $indiciaUserId)) {
        hostsite_goto_page('<front>');
        return;
      }
      $r .= $this->showGroupPage($group, $config->get('website_id'), $auth['read']);
      return [
        '#markup' => Markup::create($r),
      ];
    }
    else {
      // User is not logged in, so redirect to login page with parameters so we
      // know which group.
      hostsite_goto_page('user', [
        'group_id' => $group['id'],
        'destination' => \Drupal::request()->query->get('q'),
      ]);
    }
  }

  /**
   * Callback for shared group join links with title.
   *
   * @param string $title
   *   URL formatted name of the group.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   */
  public function joinGroupCallbackWithTitle($title, Request $request) {
    return $this->joinGroupCallback($title, NULL, $request);

  }

  /**
   * Callback for shared group join links with title and parent title.
   *
   * @param string $title
   *   URL formatted name of the group.
   * @param string $parentTitle
   *   URL formatted name of the parent group.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   */
  public function joinGroupCallbackWithParentAndTitle($title, $parentTitle, Request $request) {
    return $this->joinGroupCallback($title, $parentTitle, $request);
  }

  /**
   * Return a readable group title.
   *
   * Take account of the different way group titles are written to make it
   * easier to create readable sentences about a group. Basically adds
   * " group" to the end of the group title if not already there.
   *
   * @param array $group
   *   Group record loaded from database.
   *
   * @return string
   *   Group title.
   */
  function readableGroupTitle($group) {
    $r = $group['title'];
    if (!preg_match('/ ' . t('group') . '$/', $r)) {
      $r .= ' ' . $this->t('group');
    }
    return $r;
  }

  /**
   * Displays a HTML block that describes a group.
   *
   * Including the logo, title, description and available page links.
   *
   * @param array $group
   *   Group details.
   * @param int $websiteId
   *   Website ID.
   * @param array $readAuth
   *   Read authorisation tokens.
   *
   * @return string
   *   Group description HTML.
   */
  function showGroupPage(array $group, $websiteId, array $readAuth) {
    $path = \data_entry_helper::get_uploaded_image_folder();
    $img = empty($group['logo_path']) ? '' : "<img style=\"width: 20%; float: left; padding-right: 5%\" alt=\"Logo\" src=\"$path$group[logo_path]\"/>";
    $r = '<div class="clearfix">' . $img . '<div style="float: left; width: 70%;">' .
        "<h3>$group[title]</h3><p class=\"group-description\">$group[description]</p>";
    $nonMembers = '';
    $adminFlags = [''];
    if (empty($group['member']) || $group['member'] === 'f') {
      $nonMembers = ' non-members of';
    }
    elseif (!empty($group['member']) && $group['member'] === 't') {
      $adminFlags[] = 'f';
    }
    if (!empty($group['administrator']) && $group['administrator'] === 't') {
      $adminFlags[] = 't';
    }

    // Load the available pages.
    $pages = \data_entry_helper::get_population_data(array(
      'table' => 'group_page',
      'extraParams' => $readAuth + [
        'group_id' => $group['id'],
        'website_id' => $websiteId,
        'query' => json_encode(['in' => ['administrator' => $adminFlags]]),
      ]
    ));
    if (count($pages)) {
      $pageList = [];
      foreach ($pages as $page) {
        $class = strtolower(preg_replace('[^a-zA-Z0-9]', '-', $page['path']));
        $pageList[] = "<li><a class=\"button $class\" href=\"" .
          hostsite_get_url($page['path'], [
            'group_id' => $group['id'],
            'implicit' => $group['implicit_record_inclusion'],
          ]) .
          "\">$page[caption]</a></li>";
      }
      $pageHtml = '<ul>' . implode('', $pageList) . '</ul>';
      $r .= "<fieldset><legend>Pages</legend><p>" .
          $this->t("The following links are available for$nonMembers the @group:",
            ['@group' => $this->readableGroupTitle($group)]) . "</p>$pageHtml</fieldset>";
    }
    $r .= '</div></div>';
    return $r;
  }

  private function groupConfirmForm($group) {
    $reload = \data_entry_helper::get_reload_link_parts();
    $reloadpath = $reload['path'];
    global $indicia_templates;
    $r = '<p>' . $this->t('Would you like to join @group?', ['@group' => $group['title']]) . '</p>';
    $r .= "<form method=\"GET\" action=\"$reloadpath\">";
    foreach ($reload['params'] as $key => $value) {
      $r .= "<input type=\"hidden\" name=\"$key\" value=\"$value\" />";
    }
    $r .= '<input type="hidden" name="confirmed" value="t" />';
    $r .= "<input type=\"submit\" value=\"" . $this->t('Join') . "\" class=\"$indicia_templates[buttonHighlightedClass]\"/>";
    $r .= '</form>';
    return $r;
  }

  /**
   * Joins a given user to a recording group.
   *
   * After joining, shows a list of options related to the group or redirects to
   * the group's page if there is only one.
   *
   * @param array $group
   *   Group data loaded from the database. Will be updated with new membership
   *   status.
   * @param array $writeAuth
   *   Write authorisation tokens.
   * @param int $indiciaUserId
   *   User's warehouse user ID.
   *
   * @return bool
   *   True if joining was successful.
   */
  function joinPublicGroup(&$group, $writeAuth, $indiciaUserId) {
    $conn = iform_get_connection_details();
    $userName = hostsite_get_user_field('name');
    $values = [
      'website_id' => $conn['website_id'],
      'groups_user:group_id' => $group['id'],
      'groups_user:user_id' => $indiciaUserId,
      'groups_user:username' => $userName,
      // Pending if group is by request.
      'groups_user:pending' => $group['joining_method_raw'] === 'P' ? 'f' : 't',
    ];
    if (!empty($group['groups_user_id'])) {
      // Existing record to update?
      $values['groups_user:id'] = $group['groups_user_id'];
    }
    $s = \submission_builder::build_submission($values, ['model' => 'groups_user']);
    $r = \data_entry_helper::forward_post_to('save', $s, $writeAuth);
    if (isset($r['success'])) {
      if ($group['joining_method_raw'] === 'R') {
        $this->messenger->addMessage($this->t('Your request to join @group has been logged and is waiting for approval.',
          ['@group' => $this->readableGroupTitle($group)]));
      }
      else {
        $this->messenger->addMessage($this->t('Welcome, you are now a member of @group!',
          ['@group' => $this->readableGroupTitle($group)]));
      }
      // Update the new membership status in the group object.
      if ($group['joining_method'] === 'R') {
        $group['pending'] = 't';
      }
      else {
        $group['member'] = 't';
      }
      return TRUE;
    }
    else {
      $this->messenger->addWarning($this->t("An error occurred whilst trying to join the @group.",
        ['@group' => $this->readableGroupTitle($group)]));
      \Drupal::logger('iform')->notice("An error occurred whilst trying to join $group[title] for {$userName}.");
      \Drupal::logger('iform')->notice(var_export($r, TRUE));
      return FALSE;
    }
  }

}
