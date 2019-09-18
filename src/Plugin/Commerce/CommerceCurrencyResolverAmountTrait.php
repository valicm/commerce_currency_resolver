<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce;

use Drupal\commerce_currency_resolver\CommerceCurrencyResolverTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;
use Drupal\commerce_price\Price;

/**
 * Provides common configuration for fixed amount off offers.
 */
trait CommerceCurrencyResolverAmountTrait {

  use CommerceCurrencyResolverTrait;

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
    $defaultCurrency = $this->fallbackCurrencyCode();

    // If we handle shipping.
    if (isset($form['rate_amount']) && empty($form['rate_amount']['#default_value'])) {
      $form['rate_amount']['#default_value'] = [
        'number' => '',
        'currency_code' => $defaultCurrency,
      ];
    }

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
    $enabledCurrencies = $this->getEnabledCurrencies();

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
   * @param string $target_currency
   *   Currency code resolved from CurrentCurrency.
   *
   * @return \Drupal\commerce_price\Price
   *   Return Price object or FALSE.
   */
  public function getPrice(Price $input_price, string $target_currency) {

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
    return CurrencyHelper::priceConversion($input_price, $target_currency);

  }

  /**
   * Convert prices for Commerce condition or Promotion offers.
   *
   * @param \Drupal\commerce_price\Price $input_price
   *   Price which is received from order.
   * @param \Drupal\commerce_price\Price $check_price
   *   Price which is in condition or promotion offer entered.
   *
   * @return \Drupal\commerce_price\Price
   *   Return Price object.
   */
  public function convertPrice(Price $input_price, Price $check_price) {

    // We rely on order price.
    $currentCurrency = $input_price->getCurrencyCode();

    // If we have specified price listed.
    if (isset($this->configuration['fields'][$currentCurrency])) {
      $priceField = $this->configuration['fields'][$currentCurrency];

      // Added check if prices is empty
      // (etc. after migration of old discounts).
      if (!empty($priceField['number'])) {
        return new Price($priceField['number'], $priceField['currency_code']);
      }
    }

    return CurrencyHelper::priceConversion($check_price, $currentCurrency);
  }

  /**
   * Do not run conditions currency conversion on specific conditions.
   *
   * @return bool
   *   Return TRUE if is allowed.
   */
  public function shouldCurrencyRefresh() {
    return !(PHP_SAPI === 'cli');
  }

}
