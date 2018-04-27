<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_currency_resolver\CurrentCurrency;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\OrderRefreshInterface;

/**
 * Checking for mismatch in currencies on order.
 *
 * @package Drupal\commerce_currency_resolver\EventSubscriber
 */
class OrderCurrencyRefresh implements EventSubscriberInterface {

  /**
   * The order refresh.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

  /**
   * The order storage.
   *
   * @var \Drupal\commerce_order\OrderStorage
   */
  protected $orderStorage;

  /**
   * Current currency.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency, OrderRefreshInterface $order_refresh) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->currentCurrency = $currency;
    $this->orderRefresh = $order_refresh;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.commerce_order.load' => 'checkCurrency',
    ];
    return $events;
  }

  /**
   * Check for misplace in currency. Refresh order if necessary.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function checkCurrency(OrderEvent $event) {
    // Get order data.
    $order = $event->getOrder();
    $total = $order->getTotalPrice();
    $subtotal = $order->getSubtotalPrice();

    // Check if we order total and subtotal is not empty.
    // Case when you add product first time to cart, order total does not exist.
    // So we don't need to do anything here.
    if (!empty($subtotal) && !empty($total)) {
      // Get order total and subtotal currency.
      $currency_total = $total->getCurrencyCode();
      $currency_subtotal = $subtotal->getCurrencyCode();

      // Get main currency.
      $currency_main = $this->currentCurrency->getCurrency();

      // Compare order subtotal and main resolved currency.
      // Refresh order if they are different.
      // We are comparing with order subtotal, not total
      // while total_price causes loop for refresh. Deal separately with
      // order total.
      if ($currency_subtotal !== $currency_main) {
        // Refresh order.
        $this->orderRefresh->refresh($order);
      }

      // Check order total. Convert if needed for display purposes.
      // TODO: find a better way for this.
      if ($currency_total !== $currency_main) {
        $order->recalculateTotalPrice();
      }
    }
  }

}
