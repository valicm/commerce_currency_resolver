<?php

namespace Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides common configuration for fixed amount off offers.
 */
trait ExchangerOrderFixedAmountTrait {

  use ExchangerConditionTrait;

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, EntityInterface $commerce_entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    // An order without items has no total price yet. getConditionAmount()
    // takes NULL and falls back to the resolved currency, and the parent
    // bails out on its own when there is nothing to discount.
    $this->configuration['amount'] = $this->getConditionAmount($order->getTotalPrice()?->getCurrencyCode());

    parent::apply($entity, $commerce_entity);
  }

}
