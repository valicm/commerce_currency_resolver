<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\Condition;

use Drupal\commerce_currency_resolver\CurrencyHelper;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_price\Price;

/**
 * Provides the total price condition for orders per currency.
 *
 * @CommerceCondition(
 *   id = "order_total_price_currency",
 *   label = @Translation("Total price"),
 *   display_label = @Translation("Current order total per currency"),
 *   category = @Translation("Order"),
 *   entity_type = "commerce_order",
 * )
 *
 * @see \Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice
 */
class CommerceCurrencyResolverOrderTotalPrice extends OrderTotalPrice {

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
  public function getAutoCaclulateValues() {
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
      '#options' => $this->getAutoCaclulateValues(),
      '#default_value' => $this->configuration['autocalculate'],
      '#required' => TRUE,
    ];

    // Get all enabled currencies.
    $enabledCurrencies = CurrencyHelper::getEnabledCurrency();

    // Remove default from the list.
    unset($enabledCurrencies[$defaultCurrency]);

    $form['fields'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Price per currency'),
      '#tree' => TRUE,
    ];

    foreach ($enabledCurrencies as $key => $currency) {

      $amount_key = isset($this->configuration['fields'][$key]) ? $this->configuration['fields'][$key] : NULL;

      // An #ajax bug can cause $amount to be incomplete.
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

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['operator'] = $values['operator'];
    $this->configuration['amount'] = $values['amount'];
    $this->configuration['fields'] = $values['fields'];
    $this->configuration['autocalculate'] = $values['autocalculate'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $total_price = $order->getTotalPrice();
    $condition_price = new Price($this->configuration['amount']['number'], $this->configuration['amount']['currency_code']);
    if ($total_price->getCurrencyCode() !== $condition_price->getCurrencyCode()) {

      // If we have autocalculate option enabled, transfer condition price to
      // order currency code.
      if (!empty($this->configuration['autocalculate'])) {
        $currentCurrency = $total_price->getCurrencyCode();
        $condition_price = CurrencyHelper::priceConversion($condition_price, $currentCurrency);
      }

      else {
        // If we have specified price listed.
        if (isset($this->configuration['fields'][$total_price->getCurrencyCode()])) {
          $priceField = $this->configuration['fields'][$total_price->getCurrencyCode()];
          $condition_price = new Price($priceField['number'], $priceField['currency_code']);
        }

        // Fallback to FALSE.
        else {
          return FALSE;
        }
      }
    }

    switch ($this->configuration['operator']) {
      case '>=':
        return $total_price->greaterThanOrEqual($condition_price);

      case '>':
        return $total_price->greaterThan($condition_price);

      case '<=':
        return $total_price->lessThanOrEqual($condition_price);

      case '<':
        return $total_price->lessThan($condition_price);

      case '==':
        return $total_price->equals($condition_price);

      default:
        throw new \InvalidArgumentException("Invalid operator {$this->configuration['operator']}");
    }
  }

}
