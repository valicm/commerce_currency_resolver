<?php

namespace Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides common configuration for order item fixed amount off offers.
 */
trait ExchangerOrderItemFixedAmountTrait {

  use ExchangerConditionTrait;

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, EntityInterface $commerce_entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $entity;
    $this->configuration['amount'] = $this->getConditionAmount($order_item->getTotalPrice()->getCurrencyCode());
    parent::apply($entity, $commerce_entity);
  }

}
