<?php

namespace Drupal\Iform;

use Drupal\Core\StringTranslation\StringTranslationTrait;

class IformPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of permissions.
   * Generates dynamic permissions required for Indicia forms.
   *
   * @return array
   */
  public function permissions() {
    $permissions = [];
    $helpersLoaded = false;
    // Get list of iform nodes
    $query = \Drupal::entityQuery('node')->condition('type', 'iform_page');
    $nids = $query->execute();
    foreach ($nids as $nid) {
      $node = \Drupal\node\Entity\Node::load($nid);
      if ($node->field_iform->value) {
        if (!$helpersLoaded) {
          iform_load_helpers(array('data_entry_helper'));
          $helpersLoaded=true;
        }
        require_once iform_client_helpers_path() . 'prebuilt_forms/' . $node->field_iform->value . '.php';
        if (method_exists('iform_' . $node->field_iform->value, 'get_perms')) {
          $perms = call_user_func(array('iform_' . $node->field_iform->value, 'get_perms'), $node->id(), $node->params);
          foreach($perms as $perm) {
            $permissions[$perm] = [
              'title' => $this->t($perm),
              'description' => t('Permission generated by Iform prebuilt form')
            ];
          }
        }
      }
      if (!empty($node->params['view_access_control'])) {
        if (!empty($node->params['permission_name'])) {
          $perm = $node->params['permission_name'];
        }
        else {
          $perm = 'access iform ' . $node->id();
        }
        $permissions[$perm] = [
          'title' => $this->t($perm),
          'description' => t('Permission generated by Iform prebuilt form')
        ];
      }
    }
    return $permissions;
  }
}
?>