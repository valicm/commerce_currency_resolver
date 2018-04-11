<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;

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

    // Get current settings.
    $config = $this->config('commerce_currency_resolver.settings');

    // Get active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();

    $form['currency_mapping'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency mapping'),
      '#description' => $this->t('Select how currency is mapped in the system. By country, language.'),
      '#options' => CurrencyHelper::getAvailableMapping(),
      '#default_value' => $config->get('currency_mapping'),
      '#required' => TRUE,
    ];

    $form['currency_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency source'),
      '#description' => $this->t('Select how currency is added and calculated. To avoid possible errors, "Combo mode" is best option. If field for currency does not exist, it will fallback to automatic conversion for this specific currency'),
      '#options' => [
        'auto' => $this->t('Automatic conversion'),
        'field' => $this->t('Price field per currency'),
        'combo' => $this->t('Combo mode'),
      ],
      '#default_value' => $config->get('currency_source'),
      '#required' => TRUE,
    ];

    $form['currency_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Default currency'),
      '#description' => $this->t('Select currency which you consider default - as fallback if all resolvers fails, and upon exchange rates should be calculated'),
      '#options' => $active_currencies,
      '#default_value' => $config->get('currency_default'),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_currency_resolver.settings');

    // Set values.
    $config->set('currency_mapping', $form_state->getValue('currency_mapping'))
      ->set('currency_source', $form_state->getValue('currency_source'))
      ->set('currency_default', $form_state->getValue('currency_default'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
