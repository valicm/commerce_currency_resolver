<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency, OrderRefreshInterface $order_refresh, RouteMatchInterface $route_match) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->currentCurrency = $currency;
    $this->orderRefresh = $order_refresh;
    $this->routeMatch = $route_match;
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

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getOrder();

    // See if order is considered as cart.
    $cart = (bool) $order->cart->get(0)->value;

    // Get current route.
    $route = $this->routeMatch->getRouteObject();
    $admin_path = \Drupal::service('router.admin_context')->isAdminRoute($route);

    // Resolve only prices on cart or checkout pages.
    // We don't want alter data on administration pages.
    // Disable this event for admin pages.
    if ($cart && !$admin_path) {

      // Get order total currency.
      if ($order_total = $order->getTotalPrice()) {
        $order_currency = $order_total->getCurrencyCode();
        $resolved_currency = $this->currentCurrency->getCurrency();

        // Compare order total and main resolved currency.
        // Refresh order if they are different. We need then alter total price.
        // This will trigger order processor which will handle
        // correction of total order price and currency.
        if ($order_currency !== $resolved_currency) {
          // Refresh order.
          $this->orderRefresh->refresh($order);
          $order->setRefreshState(Order::REFRESH_ON_LOAD);
        }
      }
    }
  }

}
