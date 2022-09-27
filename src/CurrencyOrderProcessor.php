<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Apply currency changes during the order refresh process.
 */
class CurrencyOrderProcessor implements OrderProcessorInterface {

  use CommerceCurrencyResolversRefreshTrait;

  /**
   * Current currency.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Price exchanger service.
   *
   * @var \Drupal\commerce_exchanger\ExchangerCalculatorInterface
   */
  protected $priceExchanger;

  /**
   * {@inheritdoc}
   */
  public function __construct(CurrentCurrency $currency, AccountInterface $account, ExchangerCalculatorInterface $price_exchanger) {
    $this->account = $account;
    $this->currentCurrency = $currency;
    $this->priceExchanger = $price_exchanger;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    // Skip processor if we should not refresh currency.
    if (!$this->shouldCurrencyRefresh($order)) {
      return;
    }

    // Get main currency.
    $resolved_currency = $this->currentCurrency->getCurrency();

    // Get order total.
    // Triggered on event order load to ensure that new currency and prices
    // are properly saved.
    // @see \Drupal\commerce_currency_resolver\EventSubscriber\OrderCurrencyRefresh
    if (($total = $order->getTotalPrice()) && $total->getCurrencyCode() !== $resolved_currency) {

      // Get order items.
      $items = $order->getItems();

      // Loop through all order items and find ones without PurchasableEntity
      // They need to automatically converted.
      foreach ($items as $item) {
        /** @var \Drupal\commerce_order\Entity\OrderItem $item */
        if (!$item->hasPurchasedEntity()) {
          $price = $item->getUnitPrice();
          // Auto calculate price.
          $item->setUnitPrice($this->priceExchanger->priceConversion($price, $resolved_currency));
        }
      }

      // Last part is handling adjustments. We could hit here to
      // recalculateTotalPrice(), so it makes sense to run it last.
      $new_adjustments = [];
      $reset_adjustments = FALSE;

      // Handle custom adjustments.
      if ($adjustments = $order->getAdjustments()) {
        foreach ($adjustments as $adjustment) {
          $adjustment_currency = $adjustment->getAmount()->getCurrencyCode();

          // We should only deal with locked adjustment.
          // Any non locked have their order processor implementation,
          // probably.
          if ($adjustment->isLocked() && $adjustment_currency !== $resolved_currency) {
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
      // conversion we already hit recalculateTotalPrice() with
      // $order->addAdjustment($new_adjustment), so no need again.
      if (!$reset_adjustments) {
        // Get new total price.
        $order = $order->recalculateTotalPrice();
      }

      // Use as flag for our submodules order processors.
      $order->setData(CurrencyHelper::CURRENCY_ORDER_REFRESH, TRUE);

      // Skip refreshing order - it is going cause duplicate amounts for
      // some adjustments.
      $order->setRefreshState(OrderInterface::REFRESH_SKIP);
    }

  }

}
