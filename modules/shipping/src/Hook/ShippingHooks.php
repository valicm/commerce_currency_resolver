<?php

namespace Drupal\commerce_currency_resolver_shipping\Hook;

use Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod\FlatRateCurrency;
use Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod\FlatRatePerItemCurrency;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for commerce_currency_resolver_shipping.
 */
class ShippingHooks {

  /**
   * Implements hook_commerce_shipping_method_info_alter().
   */
  #[Hook('commerce_shipping_method_info_alter')]
  public function commerceShippingMethodInfoAlter(array &$definitions): void {
    $definitions['flat_rate_per_item']['class'] = FlatRatePerItemCurrency::class;
    $definitions['flat_rate']['class'] = FlatRateCurrency::class;
  }

}
