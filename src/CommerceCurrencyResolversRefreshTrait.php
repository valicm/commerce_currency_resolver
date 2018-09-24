<?php

namespace Drupal\commerce_currency_resolver;

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
   * Check for specific access where currency resolving should not happend.
   *
   * @return bool
   *   Return true if is matched.
   */
  public function checkExcludedUrl() {
    $current_path = \Drupal::service('path.current')->getPath();
    $pathParts = explode('/', $current_path);
    // Check for any admin path.
    if (isset($pathParts[1]) && $pathParts[1] === 'admin') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get refresh state based on path.
   *
   * @return bool
   *   Return true or false.
   */
  public function stopCurrencyRefresh() {
    if ($this->checkExcludedUrl()) {
      return TRUE;
    }

    if ($this->checkAdminRoute()) {
      return TRUE;
    }

    return FALSE;
  }

}
