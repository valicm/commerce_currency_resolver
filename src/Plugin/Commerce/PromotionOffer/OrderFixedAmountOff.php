<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderFixedAmountOff as CommerceOrderFixedAmountOff;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides the fixed amount off offer for orders with multi-currency support.
 *
 * @CommercePromotionOffer(
 *   id = "order_fixed_amount_off",
 *   label = @Translation("Fixed amount off the order subtotal"),
 *   entity_type = "commerce_order",
 * )
 */
class OrderFixedAmountOff extends CommerceOrderFixedAmountOff {

  use CommerceCurrencyResolverAmountTrait;

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, PromotionInterface $promotion) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $subtotal_price = $order->getSubtotalPrice();
    $amount = $this->getAmount();

    // Check currency, make conversion if needed.
    if ($subtotal_price->getCurrencyCode() !== $amount->getCurrencyCode()) {
      // Convert prices.
      $amount = $this->convertPrice($subtotal_price, $amount);

      if (!$amount) {
        return FALSE;
      }
    }

    // The promotion amount can't be larger than the subtotal, to avoid
    // potentially having a negative order total.
    if ($amount->greaterThan($subtotal_price)) {
      $amount = $subtotal_price;
    }
    // Split the amount between order items.
    $amounts = $this->splitter->split($order, $amount);

    foreach ($order->getItems() as $order_item) {
      if (isset($amounts[$order_item->id()])) {
        $order_item->addAdjustment(new Adjustment([
          'type' => 'promotion',
          // @todo Change to label from UI when added in #2770731.
          'label' => t('Discount'),
          'amount' => $amounts[$order_item->id()]->multiply('-1'),
          'source_id' => $promotion->id(),
        ]));
      }
    }
  }

}
