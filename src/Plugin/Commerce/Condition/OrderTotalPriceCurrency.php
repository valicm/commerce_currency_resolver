<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\Condition;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice;
use Drupal\Core\Entity\EntityInterface;
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
class OrderTotalPriceCurrency extends OrderTotalPrice {

  use CommerceCurrencyResolverAmountTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $total_price = $order->getTotalPrice();
    $condition_price = new Price($this->configuration['amount']['number'], $this->configuration['amount']['currency_code']);

    // Check currency, make conversion if needed.
    if ($total_price->getCurrencyCode() !== $condition_price->getCurrencyCode()) {

      // Convert prices.
      $condition_price = $this->convertPrice($total_price, $condition_price);

      if (!$condition_price) {
        return FALSE;
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
