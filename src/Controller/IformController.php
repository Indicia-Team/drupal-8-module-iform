<?php
/**
 * @file
 * Contains \Drupal\iform\Controller\IformController.
 */

namespace Drupal\iform\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class IformController extends ControllerBase {

  /**
   * An Ajax callback for prebuilt forms, routed from iform/ajax.
   * Lets prebuilt forms expose a method called ajax_* which is then
   * available on a path iform/ajax/* for AJAX requests from the page.
   * @param string $form The filename of the form, excluding .php.
   * @param string $method The method name, excluding the ajax_ prefix.
   * @param integer $nid Node ID, used to get correct website and password. If not passed, then looks to use
   * the globally set website Id and password.
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function ajaxCallback($form, $method, $nid) {
    if ($form===NULL || $method===NULL) {
      return t('Incorrect AJAX call');
    }
    $class = "\iform_$form";
    $method = "ajax_$method";
    require_once \iform_client_helpers_path() . 'prebuilt_forms/' . $form . '.php';
    if ($nid) {
      $node = \Drupal\node\Entity\Node::load($nid);
      $website_id=$node->params['website_id'];
      $password=$node->params['password'];
    }
    // if node not supplied, or does not have its own website Id and password, use the
    // global drupal vars from the settings form.
    if (!$website_id || !$password) {
      $config = \Drupal::config('iform.settings');
      $website_id=$config->get('website_id');
      $password=$config->get('password');
    }
    call_user_func(array($class, $method), $website_id, $password, $nid);
    // @todo How does the echoed response actually get to the client?
    return new Response('');
  }


}