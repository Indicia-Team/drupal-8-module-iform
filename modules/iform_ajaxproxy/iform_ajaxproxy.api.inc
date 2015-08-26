<?php

//use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/* -----------------------------------------------------------------

 Public API

------------------------------------------------------------------ */

/**
 * Provide a URL to be used for AJAX requests.
 *
 * @node string $url
 *
 * @index integer|string array index used to define how to process the post into an indicia format.
 *
 * @return string
 *   A ready to be used url.
 *
 * @see iform_proxy_redirect()
 */
function iform_ajaxproxy_url($node, $index) {
  return Url::fromRoute('iform_ajaxproxy.callback', array(),
    array('absolute' => TRUE, 'query' => array(
      'node' => $node===null ? '' : $node->nid, 'index' => $index
    )))->toString();
}