<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_currency_resolver\CommerceCurrencyResolversRefreshTrait;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_currency_resolver\CurrentCurrency;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Checking for mismatch in currencies on order.
 *
 * @package Drupal\commerce_currency_resolver\EventSubscriber
 */
class CurrencyOrderRefresh implements EventSubscriberInterface {

  use CommerceCurrencyResolversRefreshTrait;

  /**
   * The order refresh.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

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
   * {@inheritdoc}
   */
  public function __construct(CurrentCurrency $currency, OrderRefreshInterface $order_refresh, AccountInterface $account) {
    $this->account = $account;
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
    $orders = &drupal_static(__FUNCTION__, []);

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getOrder();

    // We already have fired this event.
    if (isset($orders[$order->id()])) {
      return;
    }
    // Get order total currency.
    if ($this->shouldCurrencyRefresh($order) && $order_total = $order->getTotalPrice()) {
      $order_currency = $order_total->getCurrencyCode();
      $resolved_currency = $this->currentCurrency->getCurrency();

      // Compare order total and main resolved currency.
      // Refresh order if they are different. We need then alter total price.
      // This will trigger order processor which will handle
      // correction of total order price and currency.
      if ($order_currency !== $resolved_currency) {

        // Set as flag to trigger this even once.
        $orders[$order->id()] = TRUE;

        // Check if we can refresh order.
        $this->orderRefresh->refresh($order);
      }
    }
  }

}
