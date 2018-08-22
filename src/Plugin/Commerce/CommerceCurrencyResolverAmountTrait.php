<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce;

use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;

/**
 * Provides common configuration for fixed amount off offers.
 */
trait CommerceCurrencyResolverAmountTrait {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'autocalculate' => 0,
    ] + parent::defaultConfiguration();
  }

  /**
   * Gets the autocalculate value.
   *
   * @return array
   *   The autocalculate values.
   */
  public function getAutoCalculateValues() {
    return [
      0 => $this->t('No'),
      1 => $this->t('Yes'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Get default currency.
    $defaultCurrency = \Drupal::config('commerce_currency_resolver.settings')
      ->get('currency_default');

    $form['amount']['#available_currencies'] = [$defaultCurrency];

    $form['autocalculate'] = [
      '#type' => 'radios',
      '#title' => t('Autocalculate'),
      '#description' => t('If you want to ignore specific currency fields, you cou'),
      '#options' => $this->getAutoCalculateValues(),
      '#default_value' => $this->configuration['autocalculate'],
      '#required' => TRUE,
    ];

    $form['fields'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Amount per currency'),
      '#tree' => TRUE,
    ];

    // Get all enabled currencies.
    $enabledCurrencies = CurrencyHelper::getEnabledCurrency();

    // Remove default from the list.
    unset($enabledCurrencies[$defaultCurrency]);

    foreach ($enabledCurrencies as $key => $currency) {

      $amount_key = isset($this->configuration['fields'][$key]) ? $this->configuration['fields'][$key] : NULL;

      // An #ajax bug can cause $amount_key to be incomplete.
      if (isset($amount_key) && !isset($amount_key['number'], $amount_key['currency_code'])) {
        $amount_key = NULL;
      }

      $form['fields'][$key] = [
        '#type' => 'commerce_price',
        '#title' => t('Amount'),
        '#default_value' => $amount_key,
        '#required' => FALSE,
        '#available_currencies' => [$key],
      ];
    }


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['fields'] = $values['fields'];
      $this->configuration['autocalculate'] = $values['autocalculate'];
    }
  }

}
