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
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'multicurrency' => 1,
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

    $form['multicurrency'] = [
      '#type' => 'radios',
      '#title' => $this->t('Use multicurrency'),
      '#description' => $this->t('If you want to use multicurrency logic select yes, otherwise it will be ignored and processed without multicurrency resolver.'),
      '#options' => $this->getAutoCalculateValues(),
      '#default_value' => $this->configuration['multicurrency'],
      '#required' => TRUE,
    ];

    $form['autocalculate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocalculate'),
      '#description' => $this->t('By current exchange rates it calculates amount in other currencies - ignores completely fields per currency.'),
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
      $this->configuration['autocalculate'] = $values['autocalculate'];
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
   * @return bool|\Drupal\commerce_price\Price
   *   Return Price object or FALSE.
   */
  public function getPrice(Price $input_price, $target_currency) {

    // Defaults.
    $calculatedPrice = FALSE;

    // If we have autocalculate option enabled, transfer condition price to
    // order currency code.
    if (!$this->configuration['autocalculate']) {

      // If we have specified price listed.
      if (isset($this->configuration['fields'][$target_currency])) {
        $priceField = $this->configuration['fields'][$target_currency];

        // Added check if prices is empty
        // (etc. after migration of old discounts).
        if (!empty($priceField['number'])) {
          $calculatedPrice = new Price($priceField['number'], $priceField['currency_code']);
        }
      }
    }

    // Always autocalculate. Even if autocalculate is not selected.
    // Reason is that price field could be empty, and we will get different
    // price and Currency mismatch.
    // Second reason is that exist flag "use multicurrency" if site owner
    // want's to make condition without use of multicurrency or fear that
    // something will be autocalculated.
    if (!$calculatedPrice) {
      $calculatedPrice = CurrencyHelper::priceConversion($input_price, $target_currency);
    }

    return $calculatedPrice;

  }

  /**
   * Convert prices for Commerce condition or Promotion offers.
   *
   * @param \Drupal\commerce_price\Price $input_price
   *   Price which is received from order.
   * @param \Drupal\commerce_price\Price $check_price
   *   Price which is in condition or promotion offer entered.
   *
   * @return bool|\Drupal\commerce_price\Price
   *   Return FALSE or Price object.
   */
  public function convertPrice($input_price, $check_price) {

    // Defaults.
    $calculatedPrice = FALSE;

    // We rely on order price.
    $currentCurrency = $input_price->getCurrencyCode();

    // If we have autocalculate option enabled, transfer condition price to
    // order currency code.
    if (!empty($this->configuration['autocalculate'])) {
      // Convert prices.
      $calculatedPrice = CurrencyHelper::priceConversion($check_price, $currentCurrency);
    }

    else {
      // If we have specified price listed.
      if (isset($this->configuration['fields'][$input_price->getCurrencyCode()])) {
        $priceField = $this->configuration['fields'][$input_price->getCurrencyCode()];

        // Added check if prices is empty
        // (etc. after migration of old discounts).
        if (!empty($priceField['number'])) {
          $calculatedPrice = new Price($priceField['number'], $priceField['currency_code']);
        }
      }
    }

    // Fallback always on autocalculate regardless of setting.
    // TODO: refactor later this entire function.
    if (!$calculatedPrice) {
      $calculatedPrice = CurrencyHelper::priceConversion($check_price, $currentCurrency);
    }

    return $calculatedPrice;
  }

  /**
   * Do not run conditions currency conversion on specific conditions.
   *
   * @return bool
   *   Return TRUE if is allowed.
   */
  public function shouldCurrencyRefresh() {

    // Disallow in cli mode.
    if (PHP_SAPI === 'cli') {
      return FALSE;
    }

    // Check if multicurrency is enabled on condition.
    if ($this->configuration['multicurrency']) {
      return TRUE;
    }

    return FALSE;
  }

}
