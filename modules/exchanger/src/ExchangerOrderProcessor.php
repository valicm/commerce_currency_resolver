<?php

namespace Drupal\commerce_currency_resolver_exchanger;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;

/**
 * Apply currency changes during the order refresh process.
 */
class ExchangerOrderProcessor implements OrderProcessorInterface {

  public function __construct(protected CurrencyResolverManagerInterface $currencyResolverManager, protected CurrentCurrencyInterface $currentCurrency, protected ExchangerCalculatorInterface $priceExchanger) {}

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    $current_currency = $this->currentCurrency->getCurrency();
    // Skip the processor if we should not refresh currency.
    if (!$this->currencyResolverManager->shouldCurrencyRefresh($order, $current_currency)) {
      return;
    }

    $resolved_currency = $current_currency->getCurrencyCode();

    // Get order items.
    $items = $order->getItems();

    // Loop through all order items and find ones without PurchasableEntity
    // They need to automatically convert.
    foreach ($items as $item) {
      /** @var \Drupal\commerce_order\Entity\OrderItem $item */
      if (!$item->hasPurchasedEntity()) {
        $price = $item->getUnitPrice();
        // Auto calculate price.
        $item->setUnitPrice($this->priceExchanger->priceConversion($price, $resolved_currency));
      }

      // Compatibility with Commerce VADO.
      if ($vado_discounted_price = $item->getData('commerce_vado_discount_price')) {
        $item->setData('commerce_vado_discount_price', $this->priceExchanger->priceConversion($vado_discounted_price, $resolved_currency));
      }
    }

    // The last part is handling adjustments. We could hit here to
    // recalculateTotalPrice(), so it makes sense to run it last.
    $new_adjustments = [];
    $reset_adjustments = FALSE;

    // Handle custom adjustments.
    if ($adjustments = $order->getAdjustments()) {
      foreach ($adjustments as $adjustment) {
        $adjustment_currency = $adjustment->getAmount()->getCurrencyCode();

        // We should only deal with locked adjustment.
        // Any non locked has their order processor implementation,
        // probably.
        if ($adjustment_currency !== $resolved_currency && $adjustment->isLocked()) {
          $reset_adjustments = TRUE;
          $adjustment_amount = $adjustment->getAmount();
          $values = $adjustment->toArray();
          // Auto calculate price.
          $values['amount'] = $this->priceExchanger->priceConversion($adjustment_amount, $resolved_currency);
          $new_adjustment = new Adjustment($values);
          $new_adjustments[] = $new_adjustment;
        }
      }

      // We have custom adjustments which need to be recalculated.
      if ($reset_adjustments) {
        // We need clear adjustments like that while using
        // $order->removeAdjustment() will trigger recalculateTotalPrice()
        // which will break everything, while currencies are different.
        $order->set('adjustments', []);

        foreach ($new_adjustments as $new_adjustment) {
          $order->addAdjustment($new_adjustment);
        }
      }
    }

    // Flag for recalculating order. If we had custom adjustments for
    // conversion, we already hit recalculateTotalPrice() with
    // $order->addAdjustment($new_adjustment), so no need again.
    if (!$reset_adjustments) {
      // Get new total price.
      $order = $order->recalculateTotalPrice();
    }

    // Use as a flag for our submodule order processors.
    $order->setData(CurrencyResolverManagerInterface::CURRENCY_ORDER_REFRESH, TRUE);
    // Clear always this value.
    if ($order->getData(CurrencyResolverManagerInterface::CURRENCY_RESOLVER_FORCE_REFRESH)) {
      $order->setData(CurrencyResolverManagerInterface::CURRENCY_RESOLVER_FORCE_REFRESH, NULL);
    }

    // Skip refreshing order - it is going cause duplicate amounts for
    // some adjustments.
    $order->setRefreshState(OrderInterface::REFRESH_SKIP);
  }

}
