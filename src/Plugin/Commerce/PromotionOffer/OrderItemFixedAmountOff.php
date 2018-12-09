<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderItemFixedAmountOff as CommerceOrderItemFixedAmountOff;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides the percentage off offer for order items with multicurrency support.
 *
 * @CommercePromotionOffer(
 *   id = "order_item_fixed_amount_off",
 *   label = @Translation("Fixed amount off each matching product"),
 *   entity_type = "commerce_order_item",
 * )
 */
class OrderItemFixedAmountOff extends CommerceOrderItemFixedAmountOff {

  use CommerceCurrencyResolverAmountTrait;

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, PromotionInterface $promotion) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $entity;
    $total_price = $order_item->getTotalPrice();
    $amount = $this->getAmount();

    // Check if we need to use multicurrency logic.
    if ($this->shouldCurrencyRefresh()) {
      // Check currency, make conversion if needed.
      if ($this->currentCurrency() !== $amount->getCurrencyCode()) {
        // Convert prices.
        $amount = $this->getPrice($amount, $this->currentCurrency());

        if (!$amount) {
          return;
        }
      }
    }

    $adjustment_amount = $amount->multiply($order_item->getQuantity());
    $adjustment_amount = $this->rounder->round($adjustment_amount);
    // Don't reduce the order item total price past zero.
    if ($adjustment_amount->greaterThan($total_price)) {
      $adjustment_amount = $total_price;
    }

    $order_item->addAdjustment(new Adjustment([
      'type' => 'promotion',
      // @todo Change to label from UI when added in #2770731.
      'label' => t('Discount'),
      'amount' => $adjustment_amount->multiply('-1'),
      'source_id' => $promotion->id(),
    ]));
  }

}
