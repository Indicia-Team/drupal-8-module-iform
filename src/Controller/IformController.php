<?php

namespace Drupal\iform\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IformController extends ControllerBase {

  /**
   * Messenger service.
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
    require_once \iform_client_helpers_path() . 'prebuilt_forms/' . $form . '.php';
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
    if (isset($node->params['base_url']) && $node->params['base_url'] !== $config->get('base_url')) {
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
  function esproxyCallback($method, $nid = NULL) {
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
   * @param int $nid
   *   Optional node ID if site wide ES configuration to be overridden.
   *
   * @return object
   *   Drupal response.
   */
  function dynamicattrsproxyCallback($method) {
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
   *   Optional URL formatted name of the parent group.
   */
  public function joinGroupCallback($title, $parentTitle = NULL) {
    iform_load_helpers(['report_helper']);
    $config = \Drupal::config('iform.settings');
    $auth = \report_helper::get_read_write_auth($config->get('website_id'), $config->get('password'));
    $indiciaUserId = hostsite_get_user_field('indicia_user_id', 0);
    $params = array(
      'title' => $title,
      'currentUser' => $indiciaUserId
    );
    if ($parentTitle)
      $params['parent_title'] = $parentTitle;
    // Look up the group.
    $groups = \report_helper::get_report_data(array(
      'dataSource' => 'library/groups/find_group_by_url',
      'readAuth' => $auth['read'],
      'extraParams' => $params
    ));
    if (isset($groups['error'])) {
      $this->messenger->addWarning($this->t('An error occurred when trying to access the group.'));
      \Drupal::logger('iform')->notice('Groups page load error: ' . var_export($groups, true));
      hostsite_goto_page('<front>');
      return;
    }
    if (!count($groups)) {
      $this->messenger->addWarning($this->t('The group you are trying to join does not appear to exist.'));
      throw new NotFoundHttpException();
      return;
    }
    if (count($groups) > 1) {
      $this->messenger->addWarning($this->t('The group you are trying to join has a duplicate name with another group so cannot be joined in this way.'));
      hostsite_goto_page('<front>');
    }
    $group = $groups[0];
    if ($group['member']==='t') {
      $this->messenger->addMessage($this->t("Welcome back to the @group.", ['@group' => iform_readable_group_title($group)]));
      return iform_show_group_page($group, $auth['read']);
    }
    elseif ($group['joining_method_raw']==='I') {
      $this->messenger->addWarning($this->t('The group you are trying to join is private.'));
      hostsite_goto_page('<front>');
      return;
    }
    global $user;
    if ($user->uid) {
      $r = '';
      // User is logged in
      if (!$indiciaUserId) {
        $this->messenger->addMessage($this->t("Before joining $group[title], please set your surname on your user account profile."));
        hostsite_goto_page('<front>');
        return;
      }
      elseif ($group['pending']==='t' && $group['joining_method'] !== 'P') {
        // Membership exists but is pending.
        $this->messenger->addMessage($this->t('Your application to join @group is still waiting for a group administrator to approve it.', array('@group' => iform_readable_group_title($group))));
      }
      elseif (!isset($_GET['confirmed'])) {
        $r .= _iform_group_confirm_form($group);
      }
      elseif (!iform_join_public_group($group, $auth['write_tokens'], $indiciaUserId)) {
        hostsite_goto_page('<front>');
        return;
      }
      $r .= iform_show_group_page($group, $auth['read']);
      return $r;
    }
    else {
      // User is not logged in, so redirect to login page with parameters so we know which group
      hostsite_goto_page('user', ['group_id' => $group['id'], 'destination' => $_GET['q']]);
    }
  }

}
