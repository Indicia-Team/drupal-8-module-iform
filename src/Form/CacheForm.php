<?php

/**
 * @file
 * Contains \Drupal\iform\Form\SettingsForm.
 */

namespace Drupal\iform\Form;

use Drupal\Core\Form\FormBase;

class CacheForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'iform_cache_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = array();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {

  }

  /**
   * Utility function to populate the list of warehouses in the global $_iform_warehouses. Each warehouse is loaded from an .inc
   * file in the warehouses sub-folder
   * @todo Remove need for global
   */
  private function load_warehouse_array() {
    global $_iform_warehouses;
    $_iform_warehouses = array();
    foreach (glob(drupal_get_path('module', 'iform') . '/warehouses/*.inc') as $warehouse_file) {
      require($warehouse_file);
    }
  }

}