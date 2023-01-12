<?php

namespace Drupal\iform_inlinejs\Asset;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Asset\JsCollectionRenderer;
use Drupal\Component\Render\FormattableMarkup;

/**
 * A renderer for Indicia inline JS.
 */
class IformInlineJsCollectionRenderer extends JsCollectionRenderer {

  /**
   * {@inheritdoc}
   *
   * Most of the code is from JsCollectionRenderer::render(). We just position
   * inline JS with $is_header flag.
   */
  public function render(array $js_assets) {
    $elements = [];
    $isIformInit = FALSE;

    // A dummy query-string is added to filenames, to gain control over
    // browser-caching. The string changes on every update or full cache
    // flush, forcing browsers to load a new copy of the files, as the
    // URL changed. Files that should not be cached get REQUEST_TIME as
    // query-string instead, to enforce reload on every page request.
    $default_query_string = $this->state->get('system.css_js_query_string') ?: '0';

    // Defaults for each SCRIPT element.
    $element_defaults = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => '',
    ];

    // Loop through all JS assets.
    foreach ($js_assets as $js_asset) {
      // Element properties that do not depend on JS asset type.
      $element = $element_defaults;
      $element['#browsers'] = $js_asset['browsers'];

      // Element properties that depend on item type.
      switch ($js_asset['type']) {
        case 'setting':
          $element['#attributes'] = [
            // This type attribute prevents this from being parsed as an
            // inline script.
            'type' => 'application/json',
            'data-drupal-selector' => 'drupal-settings-json',
          ];
          $element['#value'] = Json::encode($js_asset['data']);
          break;

        case 'file':
          $isIformInit = $isIformInit || (substr($js_asset['data'], -20) === 'indicia.functions.js');
          $query_string = $js_asset['version'] == -1 ? $default_query_string : 'v=' . $js_asset['version'];
          $query_string_separator = (strpos($js_asset['data'], '?') !== FALSE) ? '&' : '?';
          $element['#attributes']['src'] = $this->fileUrlGenerator->generateString($js_asset['data']);
          // Only add the cache-busting query string if this isn't an aggregate
          // file.
          if (!isset($js_asset['preprocessed'])) {
            $element['#attributes']['src'] .= $query_string_separator . ($js_asset['cache'] ? $query_string : REQUEST_TIME);
          }
          break;

        case 'external':
          $element['#attributes']['src'] = $js_asset['data'];
          break;

        default:
          throw new \Exception('Invalid JS asset type.');
      }

      // Attributes may only be set if this script is output independently.
      if (!empty($element['#attributes']['src']) && !empty($js_asset['attributes'])) {
        $element['#attributes'] += $js_asset['attributes'];
      }

      $elements[] = $element;
    }
    if ($isIformInit) {
      $this->renderInlineJs($elements);
    }

    return $elements;
  }

  /**
   * Renders inline JavaScripts.
   *
   * @param array $elements
   *   An array of elements which will be updated.
   */
  protected function renderInlineJs(array &$elements) {
    // Defaults for each SCRIPT element.
    $element_defaults = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => '',
    ];
    $jsAssets = \Drupal::moduleHandler()->invokeAll('inlinejs_alter');
    // Loop through all JS assets.
    foreach ($jsAssets as $asset) {
      // Element properties that do not depend on JS asset type.
      $element = $element_defaults;
      if (isset($asset['browsers'])) {
        $element['#browsers'] = $asset['browsers'];
      }
      $element['#value'] = new FormattableMarkup($asset['data'], []);
      // Splice the inline JS after the other elements in this region.
      $elements[] = $element;
    }
  }

}
