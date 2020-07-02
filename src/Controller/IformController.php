<?php

namespace Drupal\iform\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;

class IformController extends ControllerBase {

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
      return t('Incorrect AJAX call');
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
    return new Response('', http_response_code());
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

}
