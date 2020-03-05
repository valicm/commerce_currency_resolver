<?php

namespace Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\FlatRatePerItem;

/**
 * Provides the FlatRatePerItem shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "flat_rate_per_item",
 *   label = @Translation("Flat rate per item"),
 * )
 */
class FlatRatePerItemCurrency extends FlatRatePerItem {

  use CommerceCurrencyResolverAmountTrait;

}
