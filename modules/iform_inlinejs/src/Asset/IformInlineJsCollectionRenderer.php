<?php

namespace Drupal\iform_inlinejs\Asset;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Asset\JsCollectionRenderer;

/**
 * A renderer for Indicia inline JS.
 */
class IformInlineJsCollectionRenderer extends JsCollectionRenderer {

  /**
   * {@inheritdoc}
   *
   * Extend JsCollectionRenderer->render(), adding code which detects the
   * footer assets and adds the inline JS from Indicia to the bottom.
   */
  public function render(array $js_assets) {
    $elements = parent::render($js_assets);

    $outputInlineJs = FALSE;
    foreach ($js_assets as $asset) {
      if (isset($asset['scope']) && $asset['scope'] === 'footer') {
        $outputInlineJs = TRUE;
        break;
      }
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
      // Put the script below other loaded JS files, especially jQuery.
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
