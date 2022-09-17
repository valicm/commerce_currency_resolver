<?php

namespace Drupal\commerce_currency_resolver_shipping;

use Drupal\commerce_currency_resolver\CurrencyHelper;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;

/**
 * Force refresh of shipping rates for shipment for currency changes.
 */
class ShippingCurrencyOrderProcessor implements OrderProcessorInterface {

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * Constructs a new ShippingCurrencyOrderProcessor object.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function __construct(ShippingOrderManagerInterface $shipping_order_manager) {
    $this->shippingOrderManager = $shipping_order_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    // No shipment, skip order.
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    // No need to trigger this processor.
    if (!$order->getData(CurrencyHelper::CURRENCY_ORDER_REFRESH)) {
      return;
    }

    // Unset flag.
    $order->unsetData(CurrencyHelper::CURRENCY_ORDER_REFRESH);

    // If we don't have already this flag, trigger it.
    // Otherwise, amount on shipment is going to be on old currency.
    if (!$order->getData(ShippingOrderManagerInterface::FORCE_REFRESH)) {
      $order->setData(ShippingOrderManagerInterface::FORCE_REFRESH, TRUE);
    }

  }

}
