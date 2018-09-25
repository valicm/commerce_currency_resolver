<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Handle access where currency resolver can refresh order.
 *
 * @package Drupal\commerce_currency_resolver
 */
trait CommerceCurrencyResolversRefreshTrait {

  /**
   * Check admin route.
   */
  public function checkAdminRoute() {
    // Get current route. Skip admin path.
    return \Drupal::service('router.admin_context')->isAdminRoute($this->routeMatch->getRouteObject());
  }

  /**
   * Check if order belongs to current user.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return bool
   *   Return true if this is not order owner.
   */
  public function checkOrderOwner(OrderInterface $order) {
    return (int) $order->getCustomerId() !== (int) $this->account->id();
  }

  /**
   * Check if order is in draft status.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return bool
   *   Return true if order is not in draft state.
   */
  public function checkOrderStatus(OrderInterface $order) {
    // Only draft orders should be recalculated.
    return $order->getState()->value !== 'draft';
  }

  /**
   * Get refresh state based on path.
   *
   * @return bool
   *   Return true or false.
   */
  public function shouldCurrencyRefresh(OrderInterface $order) {
    // Not owner of order.
    if ($this->checkOrderOwner($order)) {
      return FALSE;
    }

    // Order is not in draft status.
    if ($this->checkOrderStatus($order)) {
      return FALSE;
    }

    // Admin route.
    if ($this->checkAdminRoute()) {
      return FALSE;
    }

    return TRUE;
  }

}
