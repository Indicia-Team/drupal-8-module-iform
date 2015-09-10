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
    $form = array();
    if (!\iform_check_helper_config_exists()) {
      drupal_set_message(t("Please create the file helper_config.php in the !path folder on the server.",
        array('!path' => iform_client_helpers_path())), 'warning');
      $output = t('No check performed');
    }
    else {
      \iform_load_helpers(array('data_entry_helper'));
      $output =  \data_entry_helper::system_check();
    }

    $form['instruction'] = array(
      '#markup' => $output
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // nothing to do
  }

}