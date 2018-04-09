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

    $form['info'] = [
      '#type' => 'details',
      '#title' => t('Currency settings'),
      '#open' => TRUE,
    ];

    $form['info']['currency_mapping'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency mapping'),
      '#description' => $this->t('Select how currency is mapped in the system. By country, language.'),
      '#options' => CurrencyHelper::getAvailableMapping(),
      '#default_value' => $config->get('currency_mapping'),
      '#required' => TRUE,
    ];

    $form['info']['currency_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency source'),
      '#description' => $this->t('Select how currency is added and calculated. To avoid possible errors, "Combo mode" is best option. If field for currency does not exist, it will fallback to automatic conversion for this specific currency'),
      '#options' => [
        'auto' => $this->t('Automatic conversion'),
        'fields' => $this->t('Price field per currency'),
        'combo' => $this->t('Combo mode'),
      ],
      '#default_value' => $config->get('currency_source'),
      '#required' => TRUE,
    ];

    $form['currency'] = [
      '#type' => 'details',
      '#title' => t('Currency conversion'),
      '#open' => FALSE,
    ];

    $form['currency']['conversion'] = [
      '#type' => 'select',
      '#title' => $this->t('3rd party exchange service'),
      '#description' => $this->t('Select which external service you want to use for calucalting exchange rates between currencies'),
      '#options' => [
        'manual' => $this->t('Manual'),
        'external' => $this->t('External'),
        'combo' => $this->t('Combination'),
      ],
      '#default_value' => $config->get('currency_conversion'),
      '#required' => TRUE,
    ];

    $form['mapping'] = [
      '#type' => 'details',
      '#title' => t('Currency mapping'),
      '#open' => FALSE,
    ];

    $active_currencies = CurrencyHelper::getEnabledCurrency();

    // Render options in form.
    foreach ($active_currencies as $key => $item) {

      $form['currency'][$key]['value'] = [
        '#type' => 'textfield',
        '#title' => t('List countries which should use following currency: @currency', ['@currency' => $item]),
      ];

      $form['currency'][$key]['auto'] = [
        '#type' => 'checkboxes',
        '#title' => t('List countries which should use following currency: @currency', ['@currency' => $item]),
        '#options' => [1 => 'Auto']
      ];

      switch ($config->get('currency_mapping')) {
        case 'lang':
          $form['mapping'][$key] = [
            '#type' => 'select',
            '#options' => CurrencyHelper::getAvailableLanguages(),
            '#title' => t('Choose language for @currency', ['@currency' => $item]),
          ];
          break;

        case 'geo':
          $form['mapping'][$key] = [
            '#type' => 'textfield',
            '#title' => t('List countries which should use following currency: @currency', ['@currency' => $item]),
          ];
          break;
      }
    }

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
    $config = $this->config('commerce_currency_resolver.settings');

    // Set values.
    $config->set('currency_mapping', $form_state->getValue('currency_mapping'))
      ->set('currency_source', $form_state->getValue('currency_source'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
