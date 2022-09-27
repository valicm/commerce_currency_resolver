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
   * Check shipping admin route.
   */
  public function isShippingAdminRoute() {
    // Get current route. Skip admin path.
    return \Drupal::routeMatch()->getRawParameter('commerce_shipment');
  }

  /**
   * Detect admin/* paths.
   *
   * @return bool
   *   Return true if on admin/ path.
   */
  public function isAdminPath() {
    $paths = &drupal_static(__FUNCTION__, []);
    $path = \Drupal::requestStack()->getCurrentRequest()->getPathInfo();
    // Compare the lowercase path alias (if any) and internal path.
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');

    if (isset($paths[$path])) {
      return $paths[$path];
    }

    $patterns = '/admin/*';

    $paths[$path] = \Drupal::service('path.matcher')->matchPath($path, $patterns);
    return $paths[$path];
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
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object to check for currency changes.
   *
   * @return bool
   *   Return true or false.
   */
  public function shouldCurrencyRefresh(OrderInterface $order) {

    // If order have specific flag set, skip refreshing currency.
    if ($order->getData('currency_resolver_skip')) {
      return FALSE;
    }

    // Do not trigger currency refresh in cli - drush, cron, etc.
    // If we load order in cli, we don't want to manipulate order
    // with currency refresh.
    if ($this->isPhpCli()) {
      return FALSE;
    }

    if ($this->isAdminPath()) {
      return FALSE;
    }

    // Not owner of order.
    if ($this->checkOrderOwner($order)) {
      return FALSE;
    }

    // Order is not in draft status.
    if ($this->checkOrderStatus($order)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Helper function to check if we are running as CLI.
   */
  protected function isPhpCli() {
    return PHP_SAPI === 'cli';
  }

}
