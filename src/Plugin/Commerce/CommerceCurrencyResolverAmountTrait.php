<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_price\Price;

/**
 * Provides common configuration for fixed amount off offers.
 */
trait CommerceCurrencyResolverAmountTrait {

  /**
   * Get resolved currency.
   */
  public function currentCurrency() {
    return \Drupal::service('commerce_currency_resolver.current_currency')->getCurrency();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Get default currency.
    $defaultCurrency = \Drupal::service('commerce_currency_resolver.currency_helper')->fallbackCurrencyCode();

    // If we handle commerce conditions and promotions.
    if (isset($form['amount']) && empty($form['amount']['#default_value'])) {
      $form['amount']['#default_value'] = [
        'number' => '',
        'currency_code' => $defaultCurrency,
      ];
    }

    $form['fields'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#description' => $this->t('If you leave amounts per currency empty, they should be auto-calculated to avoid mismatch in currency on orders.'),
      '#title' => $this->t('Amount per currency'),
      '#tree' => TRUE,
    ];

    // Get all enabled currencies.
    $enabledCurrencies = \Drupal::service('commerce_currency_resolver.currency_helper')->getCurrencies();

    foreach ($enabledCurrencies as $key => $currency) {

      $amount_key = $this->configuration['fields'][$key] ?? NULL;

      // An #ajax bug can cause $amount_key to be incomplete.
      if (isset($amount_key) && !isset($amount_key['number'], $amount_key['currency_code'])) {
        $amount_key = NULL;
      }

      $form['fields'][$key] = [
        '#type' => 'commerce_price',
        '#title' => $this->t('Amount'),
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
    }
  }

  /**
   * Get price based on currency price and target currency.
   *
   * @param \Drupal\commerce_price\Price $input_price
   *   Default price added in condition, offer, etc.
   *
   * @return \Drupal\commerce_price\Price
   *   Return Price object.
   */
  public function getPrice(Price $input_price) {

    $target_currency = $this->currentCurrency();

    // If we have specified price listed.
    if (isset($this->configuration['fields'][$target_currency])) {
      $priceField = $this->configuration['fields'][$target_currency];

      // Added check if prices is empty
      // (etc. after migration of old discounts).
      if (!empty($priceField['number'])) {
        return new Price($priceField['number'], $priceField['currency_code']);
      }
    }

    // Auto-calculate if we don't have any price in currency field.
    return \Drupal::service('commerce_currency_resolver.calculator')->priceConversion($input_price, $target_currency);

  }

  /**
   * Do not run conditions currency conversion on specific conditions.
   *
   * @param string $currency_code
   *   Current currency on plugin.
   *
   * @return bool
   *   Return TRUE if is allowed.
   */
  public function shouldCurrencyRefresh($currency_code) {
    // No conversion for CLI tasks.
    if (PHP_SAPI === 'cli') {
      return FALSE;
    }

    // Different currencies, it is needed to refresh.
    return $this->currentCurrency() !== $currency_code;
  }

}
