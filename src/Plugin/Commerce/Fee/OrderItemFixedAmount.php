<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\Fee;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_fee\Plugin\Commerce\Fee\OrderItemFixedAmount as BaseOrderItemFixedAmount;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_fee\Entity\FeeInterface;
use Drupal\Core\Entity\EntityInterface;

class OrderItemFixedAmount extends BaseOrderItemFixedAmount {

  use CommerceCurrencyResolverAmountTrait;

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, FeeInterface $fee) {
    $this->assertEntity($entity);

    // Nothing to do. Go to parent.
    if (!$this->shouldCurrencyRefresh($this->getAmount()->getCurrencyCode())) {
      return parent::apply($entity, $fee);
    }

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $entity;
    $amount = $this->getPrice($this->getAmount());

    $adjustment_amount = $amount->multiply($order_item->getQuantity());
    $adjustment_amount = $this->rounder->round($adjustment_amount);

    $order_item->addAdjustment(new Adjustment([
      'type' => 'fee',
      'label' => $fee->getDisplayName() ?: $this->t('Fee'),
      'amount' => $adjustment_amount,
      'source_id' => $fee->id(),
    ]));
  }

}
