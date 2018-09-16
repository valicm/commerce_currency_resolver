<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Applies taxes to orders during the order refresh process.
 */
class CurrencyOrderProcessor implements OrderProcessorInterface {

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency, RouteMatchInterface $route_match) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->currentCurrency = $currency;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    // Run this processor only on cart and checkout.
    // Not on administration pages, it's depending on currentl resolved currency
    // so you could alter other people orders.
    // Get current route.
    $route = $this->routeMatch->getRouteObject();

    // Probably edit page, etc..
    if ($route === NULL) {
      return;
    }

    // Check admin path.
    $admin_path = \Drupal::service('router.admin_context')->isAdminRoute($route);

    if ($admin_path) {
      return;
    }

    // See if order is considered as cart.
    $cart = (bool) $order->cart->get(0)->value;

    // We only check cart orders.
    if (!$cart) {
      return;
    }

    // Get order total.
    $total = $order->getTotalPrice();

    // Get main currency.
    $currency_main = $this->currentCurrency->getCurrency();

    // This is used only to ensure that order have resolved currency.
    // In combination with check on order load we can ensure that currency is
    // same accross entire order.
    // This solution provides avoiding constant recalculation
    // on order load event on currency switch (if we don't explicitly set
    // currency for total price when we switch currency.
    // @see \Drupal\commerce_currency_resolver\EventSubscriber\OrderCurrencyRefresh
    if ($total->getCurrencyCode() !== $currency_main) {
      $refresh = $order->recalculateTotalPrice();
      $order->set('total_price', $refresh->getTotalPrice());
      $order->save();
    }

  }

}
