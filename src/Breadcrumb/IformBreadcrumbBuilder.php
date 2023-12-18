<?php

namespace Drupal\iform\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Breadcrumb builder for calls to hostsite_set_breadcrumb().
 */
class IformBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Determine if the current page is a node page with iform code that called
    // hostsite_set_breadcrumb.
    $node = $route_match->getParameter('node');
    if (class_exists('helper_base') && ($node || \helper_base::$force_breadcrumb) && !empty(\helper_base::$breadcrumb)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    if (class_exists('helper_base') && !empty(\helper_base::$breadcrumb)) {
      $breadcrumb->addLink(Link::createFromRoute('Home', '<front>'));
      foreach (\helper_base::$breadcrumb as $path => $caption) {
        if (substr($path, 0, 1) !== '/' && substr($path, 0, 1) !== '<') {
          $path = "/$path";
        }
        if ($caption === '#currentPageTitle#') {
          $nid = 0;
          $node = \Drupal::routeMatch()->getParameter('node');
          if ($node) {
            $nid = $node->id();
          }
          $caption = hostsite_get_page_title($nid);
        }
        else {
          $caption = $this->t($caption);
        }
        if (substr($path, 0, 1) === '/') {
          foreach ($_GET as $key => $value) {
            if (is_string($value)) {
              // GET parameters can be used as replacements.
              $path = str_replace("#$key#", $value, $path);
            }
          }
          $breadcrumb->addLink(Link::fromTextAndUrl($caption, Url::fromUserInput($path)));
        }
        else {
          // Handle routes such as <front>, <none>.
          $breadcrumb->addLink(Link::createFromRoute($caption, $path));
        }
      }
    }
    $breadcrumb->addCacheContexts(['url.query_args']);
    // Return object of type breadcrumb.
    return $breadcrumb;
  }

}
