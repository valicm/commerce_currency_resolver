<?php

namespace Drupal\commerce_currency_resolver_shipping;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;

/**
 * Force refresh of shipping rates for shipment for currency changes.
 */
class ShippingCurrencyOrderProcessor implements OrderProcessorInterface {

  /**
   * Constructs a new ShippingCurrencyOrderProcessor object.
   */
  public function __construct(protected ShippingOrderManagerInterface $shippingOrderManager) {}

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    // No shipment, skip order.
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    // No need to trigger this processor.
    if (!$order->getData(CurrencyResolverManagerInterface::CURRENCY_ORDER_REFRESH)) {
      return;
    }

    // Unset flag.
    $order->unsetData(CurrencyResolverManagerInterface::CURRENCY_ORDER_REFRESH);

    // If we don't already have this flag, trigger it.
    // Otherwise, the amount of shipment is going to be on old currency.
    if (!$order->getData(ShippingOrderManagerInterface::FORCE_REFRESH)) {
      $order->setData(ShippingOrderManagerInterface::FORCE_REFRESH, TRUE);
    }

  }

}
