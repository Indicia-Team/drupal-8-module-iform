<?php

/**
 * @file
 * Callbacks and hooks related to iform inline JS integration.
 */

/**
 * Hook to alter the inline JS.
 */
function hook_inlinescript_alter() {
  $js_asset = [];
  $js_asset[] = [
    'data' => 'alert("Hello, world!");',
  ];
  return $js_asset;
}
