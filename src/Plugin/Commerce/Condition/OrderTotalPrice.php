<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\Condition;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice as CommerceOrderTotalPrice;
use Drupal\Core\Entity\EntityInterface;
use Drupal\commerce_price\Price;

/**
 * Provides the total price condition for orders per currency.
 *
 * @CommerceCondition(
 *   id = "order_total_price",
 *   label = @Translation("Total price"),
 *   display_label = @Translation("Current order total"),
 *   category = @Translation("Order"),
 *   entity_type = "commerce_order",
 * )
 *
 * @see \Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice
 */
class OrderTotalPrice extends CommerceOrderTotalPrice {

  use CommerceCurrencyResolverAmountTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    // Nothing to do. Go to parent.
    if (!$this->shouldCurrencyRefresh($this->configuration['amount']['currency_code'])) {
      return parent::evaluate($entity);
    }

    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;

    $total_price = $order->getTotalPrice();
    $original_condition_price = new Price($this->configuration['amount']['number'], $this->configuration['amount']['currency_code']);
    $condition_price = $this->getPrice($original_condition_price);


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
