<?php

namespace Drupal\iform_inlinejs\Asset;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Asset\JsCollectionRenderer;

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
    $outputInlineJs = FALSE;

    // A dummy query-string is added to filenames, to gain control over
    // browser-caching. The string changes on every update or full cache
    // flush, forcing browsers to load a new copy of the files, as the
    // URL changed. Files that should not be cached get REQUEST_TIME as
    // query-string instead, to enforce reload on every page request.
    // For now, support D10 and D9.
    if (property_exists($this, 'assetQueryString')) {
      $default_query_string = $this->assetQueryString->get();
    }
    else {
      $default_query_string = $this->state->get('system.css_js_query_string') ?: '0';
    }

    // Defaults for each SCRIPT element.
    $element_defaults = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => '',
    ];
    // Loop through all JS assets.
    foreach ($js_assets as $js_asset) {
      $element = $element_defaults;

      // Element properties that depend on item type.
      switch ($js_asset['type']) {
        case 'setting':
          // We want the Indicia inline JS to go with the JS settings assets.
          $outputInlineJs = TRUE;
          $element['#attributes'] = [
            // This type attribute prevents this from being parsed as an
            // inline script.
            'type' => 'application/json',
            'data-drupal-selector' => 'drupal-settings-json',
          ];
          $element['#value'] = Json::encode($js_asset['data']);
          break;

        case 'file':
          $query_string = $js_asset['version'] == -1 ? $default_query_string : 'v=' . $js_asset['version'];
          $query_string_separator = str_contains($js_asset['data'], '?') ? '&' : '?';
          $element['#attributes']['src'] = $this->fileUrlGenerator->generateString($js_asset['data']);
          // Only add the cache-busting query string if this isn't an aggregate
          // file.
          if (!isset($js_asset['preprocessed'])) {
            $element['#attributes']['src'] .= $query_string_separator . ($js_asset['cache'] ? $query_string : \Drupal::time()->getRequestTime());
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

    if ($outputInlineJs) {
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
      '#weight' => 10000,
    ];
    $inlinejs_assets = \Drupal::moduleHandler()->invokeAll('inlinejs_alter');
    // Loop through all JS assets.
    foreach ($inlinejs_assets as $js_asset) {
      // Element properties that do not depend on JS asset type.
      $element = $element_defaults;
      $element['#value'] = new FormattableMarkup($js_asset['data'], []);
      // Append the inline JS after the other elements in this region.
      $elements[] = $element;
    }
  }

}
