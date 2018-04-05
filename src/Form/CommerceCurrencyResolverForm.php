<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CommerceMulticurrencySettingsForm.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceCurrencyResolverForm extends ConfigFormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_currency_resolver_admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_currency_resolver.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('commerce_currency_resolver.settings');

    $form['currency_mapping'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency mapping'),
      '#description' => $this->t('Select which logic currency is mapped'),
      '#options' => array(
        'lang' => $this->t('By Language'),
        'geo' => $this->t('By Country'),
      ),
      '#default_value' => $config->get('currency_mapping'),
      '#required' => TRUE,
    ];

    $form['currency_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency source'),
      '#description' => $this->t('Select how currency is added and calculated. To avoid possible errors, "Combo mode" is best option. If field for currency does not exist, it will fallback to automatic conversion for this specific currency'),
      '#options' => array(
        'auto' => $this->t('Automatic conversion'),
        'fields' => $this->t('Price field per currency'),
        'combo' => $this->t('Combo mode'),
      ),
      '#default_value' => $config->get('currency_source'),
      '#required' => TRUE,
    ];

    $active_currencies = \Drupal::state()->get('active_currencies');

    $form['currency_conversion'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency conversion'),
      '#description' => $this->t('Select how currency is calculated'),
      '#options' => array(
        'manual' => $this->t('Manual'),
        'external' => $this->t('External'),
        'combo' => $this->t('Combination'),
      ),
      '#default_value' => $config->get('currency_conversion'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      drupal_set_message($key . ': ' . $value);
    }

  }

}
