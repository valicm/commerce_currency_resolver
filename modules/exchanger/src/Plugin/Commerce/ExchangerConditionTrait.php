<?php

namespace Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce;

use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides common configuration for conditions.
 */
trait ExchangerConditionTrait {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'fields' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * Overrides commerce core and fee condition protected function.
   */
  protected function getConditionAmount(?string $target_currency): array {
    $amount = $this->configuration['amount'];
    $price = new Price($amount['number'], $amount['currency_code']);
    if (!$target_currency) {
      $target_currency = $this->currentCurrency->getCurrency()->getCurrencyCode();
    }
    if ($target_currency !== $amount['currency_code']) {
      // If we have specified price listed.
      if (isset($this->configuration['fields'][$target_currency])) {
        $priceField = $this->configuration['fields'][$target_currency];

        // Added check if prices is empty
        // (etc. after migration of old discounts).
        if (!empty($priceField['number'])) {
          return (new Price($priceField['number'], $priceField['currency_code']))->toArray();
        }
      }

      // Auto-calculate if we don't have any price in the currency field.
      return $this->priceExchanger->priceConversion($price, $target_currency)->toArray();
    }

    return $amount;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Get default currency.
    $defaultCurrency = $this->currentCurrency->getCurrency()?->getCurrencyCode();

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
      '#description' => $this->t('If you leave amounts per currency empty, they are going to be auto-calculated from original amount to avoid mismatch in currency on orders.'),
      '#title' => $this->t('Amount per currency'),
      '#tree' => TRUE,
    ];

    // Get all enabled currencies.
    $enabledCurrencies = $this->currencyResolverManager->getCurrencies();

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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['fields'] = $values['fields'];
    }
  }

}
