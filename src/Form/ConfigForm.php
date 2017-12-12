<?php

namespace Drupal\mage_ninja\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mage_ninja\Controller\ProductController;

class ConfigForm extends ConfigFormBase {
  /**
   * @var int PAGE_SIZE
   *  The amount of items that will be requested per page.
   */
  const PAGE_SIZE = 100;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mage_ninja.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mage_ninja_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mage_ninja.settings');

    $form['import'] = [
      '#type' => 'details',
      '#title' => t('Import'),
      '#open' => TRUE,
    ];

    $form['import']['import_all'] = [
      '#type' => 'submit',
      '#value' => t('Import all products'),
      '#submit' => ['::importAll'],
    ];

    $form['integration'] = [
      '#type' => 'details',
      '#title' => t('Magento API integration'),
      '#open' => TRUE
    ];

    $form['integration']['integration_key'] = [
      '#type' => 'item',
      '#title' => t('Integration key'),
      '#markup' => $config->get('integration_key'),
      '#description' => 'Use this integration key when activating the integration in Magento.'
    ];

    $form['integration']['integration_secret'] = [
      '#type' => 'item',
      '#title' => t('Integration secret'),
      '#markup' => $config->get('integration_secret'),
      '#description' => 'Use this integration secret when activating the integration in Magento.'
    ];

    $form['regenerate_integration'] = [
      '#type' => 'submit',
      '#value' => t('Regenerate integration key'),
      '#submit' => ['::regenerateIntegration']
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mage_ninja.settings');

    $config->set('base_uri', $form_state->getValue('base_uri'));
    $config->set('admin_username', $form_state->getValue('admin_username'));
    $config->set('admin_password', $form_state->getValue('admin_password'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Import all Magento products into Drupal.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function importAll(array &$form, FormStateInterface $form_state) {
    /** @var ProductController $controller */
    $controller = new ProductController();

    /** @var int $productCounnt */
    $productCount = $controller->getProductCount();

    /** @var int $currentPage */
    $currentPage = 1;

    /** @var array $operations */
    $operations = [];

    /** @var int $totalPages */
    // Round up to make sure the final page is also processed if it
    // contains less than PAGE_SIZE items.
    $totalPages = ceil($productCount / self::PAGE_SIZE);

    do {
      $operations[] = ['\Drupal\mage_ninja\Batch\Import::process', [$currentPage, self::PAGE_SIZE]];
      $currentPage++;
    } while($totalPages >= $currentPage);


    $batch = [
      'title' => t('MageNinja processing batch import'),
      'operations' => $operations,
      'init_message' => t('MageNinja initializing batch import'),
      'progress_message' => t('MageNinja processed @current out of @total (@remaining remaining). Estimated time remaining: @estimate (@elapsed elapsed).'),
      'error_message' => t('MageNinja encountered an error during the batch import process.')
    ];

    batch_set($batch);
  }

  public static function regenerateIntegration() {
    $integration_key = Crypt::hashBase64(uniqid(rand(), true));
    $integration_secret = Crypt::hmacBase64(uniqid(rand(), true), $integration_key);

    /** @var \Drupal\Core\Config\Config $config */
    $config = \Drupal::service('config.factory')->getEditable('mage_ninja.settings');

    $config->set('integration_key', $integration_key);
    $config->set('integration_secret', $integration_secret);

    $config->save();
  }
}