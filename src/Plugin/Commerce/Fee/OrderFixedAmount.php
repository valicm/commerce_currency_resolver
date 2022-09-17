<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\Fee;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_fee\Plugin\Commerce\Fee\OrderFixedAmount as BaseOrderFixedAmount;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_fee\Entity\FeeInterface;
use Drupal\Core\Entity\EntityInterface;

class OrderFixedAmount extends BaseOrderFixedAmount {

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

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $amount = $this->getPrice($this->getAmount());

    // Split the amount between order items.
    $amounts = $this->splitter->split($order, $amount);

    foreach ($order->getItems() as $order_item) {
      if (isset($amounts[$order_item->id()])) {
        $order_item->addAdjustment(new Adjustment([
          'type' => 'fee',
          'label' => $fee->getDisplayName() ?: $this->t('Fee'),
          'amount' => $amounts[$order_item->id()],
          'source_id' => $fee->id(),
        ]));
      }
    }
  }

}
