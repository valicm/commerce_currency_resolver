<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;

/**
 * Apply currency changes during the order refresh process.
 */
class CurrencyResolverOrderProcessor implements OrderProcessorInterface {

  public function __construct(protected CurrencyResolverManagerInterface $currencyResolverManager, protected CurrentCurrencyInterface $currentCurrency) {}

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    $current_currency = $this->currentCurrency->getCurrency();
    // Skip the processor if we should not refresh currency.
    if (!$this->currencyResolverManager->shouldCurrencyRefresh($order, $current_currency)) {
      return;
    }

    $order->recalculateTotalPrice();

    // Use as a flag for our submodule order processors.
    $order->setData(CurrencyResolverManagerInterface::CURRENCY_ORDER_REFRESH, TRUE);
    // Clear always this value.
    if ($order->getData(CurrencyResolverManagerInterface::CURRENCY_RESOLVER_FORCE_REFRESH)) {
      $order->setData(CurrencyResolverManagerInterface::CURRENCY_RESOLVER_FORCE_REFRESH, NULL);
    }

    // Skip refreshing order.
    $order->setRefreshState(OrderInterface::REFRESH_SKIP);
  }

}
