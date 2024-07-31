<?php

/**
 * @file
 * Contains \Drupal\iform\Form\CacheForm.
 */

namespace Drupal\iform\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $form['instruction'] = [
      '#markup' => '<p>' . $this->t('When Indicia requests data from the Warehouse, it can cache a local copy of ' .
          'these data to help speed up future requests for the same data. Although this makes a significant ' .
          'improvement to your website\'s performance, it can mean that changes to data are not visible ' .
          'on your website for several hours. Clear the cache to ensure that the latest copy of all data ' .
          'is loaded.') . '</p>'
    ];
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'iform_page')
      ->accessCheck(FALSE)
      ->range(0, 1);
    $nids = $query->execute();
    if (count($nids)) {
      $nid = array_pop($nids);
      global $base_url;
      $url = $base_url . \Drupal::service('path_alias.manager')->getAliasByPath("/node/$nid");
      $urlWithoutCache = $url . (strpos($url, '?') === FALSE ? '?' : '&') . 'nocache';
      $urlWithRefresh = $url . (strpos($url, '?') === FALSE ? '?' : '&') . 'refreshcache';
      $nocacheExample = ' ' . $this->t('For example, you can change the URL <a href="@url">@url</a> to <a href="@urlWithoutCache">@urlWithoutCache</a> to render the page without using the Indicia cache.',
          ['@url' => $url, '@urlWithoutCache' => $urlWithoutCache]);
      $refreshExample = ' ' . $this->t('For example, you can change the URL <a href="@url">@url</a> to <a href="@urlWithRefresh">@urlWithRefresh</a> to render the page whilst refreshing just the relevant cache entries.',
          ['@url' => $url, '@urlWithRefresh' => $urlWithRefresh]);
    }
    else {
      $nocacheExample = '';
      $refreshExample = '';
    }
    $form['nocache'] = [
      '#markup' => '<p>' . $this->t('If you want to test changes to an Indicia page without forcing a hard-reset of the entire cache, then you can add a parameter called <strong>nocache</strong> to the URL of the page to reload it without using the Indicia cache.') . " $nocacheExample</p>",
    ];
    $form['refreshcache'] = [
      '#markup' => '<p>' . $this->t('If you want to force a reset of just the cache entries used to build the current page, then you can add a parameter called <strong>refreshcache</strong> to the URL of the page to reload it and refresh the relevant cached entries.') . " $refreshExample</p>",
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Indicia cache'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    iform_load_helpers(['helper_base']);
    hostsite_cache_clear();
    $this->messenger()->addMessage(t('The Indicia cache has been cleared.'), 'status');
  }

}