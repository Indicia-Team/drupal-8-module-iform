<?php

/**
 * @file
 * Callbacks and hooks related to iform Indicia integration.
 */

/**
 * Action taken after submitting records to the warehouse.
 *
 * @param array $submission
 *   Submission data.
 * @param string $op
 *   Data operation - C(reate), U(pdate) or D(elete).
 * @param array $response
 *   Response data. Main entity affected is in $response['outer_table'] and
 *   $response['outer_id'].
 * @param string $msg
 *   Message shown to the user which may be altered by this hook.
 */
function hook_iform_after_submit(array $submission, $op, $response, &$msg) {
  // Tell the user the ID of the data saved.
  \Drupal::messenger()->addMessage(t('Record %id saved for entity %entity.', [
    '%id' => $response['outer_id'],
    '%entity' => $response['outer_table'],
  ]));
  $msg = t('Thanks!');
}
