<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;

/**
 * Class CommerceCurrencyResolverConversion.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceCurrencyResolverConversion extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_currency_resolver_admin_conversion';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_currency_resolver.currency_conversion'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get current settings.
    $config = $this->config('commerce_currency_resolver.currency_conversion');

    // Get active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();

    $form['source'] = [
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

    $form['use_cross_sync'] = array(
      '#type' => 'checkbox',
      '#default_value' => $config->get('use_cross_sync'),
      '#title' => t('Use cross conversion between non default currencies.'),
      '#description' => t('If enabled only the rates between the default currency and the other currencies have to be managed. The rates between the other currencies is derived from their rates relative to the default currency.'),
    );

    $form['demo_amount'] = array(
      '#type' => 'textfield',
      '#title' => t('Amount for example conversions:'),
      '#size' => 10,
      '#default_value' => $config->get('demo_amount'),
    );

    // Render options in form.
    foreach ($active_currencies as $key => $item) {

      $form['currency'][$key] = [
        '#type' => 'details',
        '#title' => $item,
        '#open' => FALSE,
      ];

      $form['currency'][$key]['value'] = [
        '#type' => 'textfield',
        '#title' => t('Exchange rate'),
        '#description' => t('Exchange rate from United States Dollar to @currency.', ['@currency' => $item]),
        '#size' => 20,
      ];
      $form['currency'][$key]['auto'] = [
        '#type' => 'checkboxes',
        '#title' => '',
        '#options' => [1 => 'Synchronize this conversion rate.'],
      ];

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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_currency_resolver.currency_conversion');

    // Set values.
    $config->set('source', $form_state->getValue('source'))
      ->set('use_cross_sync', $form_state->getValue('use_cross_sync'))
      ->set('demo_amount', $form_state->getValue('demo_amount'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
