<?php

/**
 * @file
 * Contains \Drupal\iform\Form\SettingsForm.
 */

namespace Drupal\iform\Form;

use Drupal\Core\Form\FormBase;

class DiagnosticsForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'iform_diagnostics_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = [];
    \iform_load_helpers(['data_entry_helper']);
    $output = \data_entry_helper::system_check();

    $form['instruction'] = [
      '#markup' => $output,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // nothing to do
  }

}