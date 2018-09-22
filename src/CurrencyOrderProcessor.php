<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_cart\CartSession;

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
   * The cart session.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  protected $cartSession;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency, RouteMatchInterface $route_match, CartSessionInterface $cart_session) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->currentCurrency = $currency;
    $this->routeMatch = $route_match;
    $this->cartSession = $cart_session;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    // Run this processor only on cart and checkout.
    // Not on administration pages, it's depending on currently resolved
    // currency so you could alter other people orders.
    // See if order is considered as cart.
    // We only check cart orders.
    $cart = (bool) $order->cart->get(0)->value;
    if (!$cart || $order->getState()->value !== 'draft') {
      return;
    }

    // Skip other peoples cart. (admin related).
    $active_cart = $this->cartSession->hasCartId($order->id(), CartSession::ACTIVE);
    if (!$active_cart) {
      return;
    }

    // Get current route. Skip admin path.
    $route = $this->routeMatch->getRouteObject();
    if (\Drupal::service('router.admin_context')->isAdminRoute($route)) {
      return;
    }

    // Get order total.
    $total = $order->getTotalPrice();

    // Get main currency.
    $resolved_currency = $this->currentCurrency->getCurrency();

    // This is used only to ensure that order have resolved currency.
    // In combination with check on order load we can ensure that currency is
    // same accross entire order.
    // This solution provides avoiding constant recalculation
    // on order load event on currency switch (if we don't explicitly set
    // currency for total price when we switch currency.
    // @see \Drupal\commerce_currency_resolver\EventSubscriber\OrderCurrencyRefresh
    if ($total->getCurrencyCode() !== $resolved_currency) {
      // Get new total price.
      $order = $order->recalculateTotalPrice();

      // Refresh order on load. Shipping fix. Probably all other potential
      // unlocked adjustments which are not set correctly.
      $order->setRefreshState(Order::REFRESH_ON_LOAD);

      // Save order.
      $order->save();
    }

  }

}
