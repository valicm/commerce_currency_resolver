<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Applies taxes to orders during the order refresh process.
 */
class CurrencyOrderProcessor implements OrderProcessorInterface {

  use CommerceCurrencyResolversRefreshTrait;

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency, AccountInterface $account, RouteMatchInterface $route_match) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->routeMatch = $route_match;
    $this->account = $account;
    $this->currentCurrency = $currency;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {

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
    if ($total->getCurrencyCode() !== $resolved_currency && $this->shouldCurrencyRefresh($order)) {
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
