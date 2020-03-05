<?php

namespace Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\FlatRate;

/**
 * Provides the multi-currency FlatRate shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "flat_rate",
 *   label = @Translation("Flat rate"),
 * )
 */
class FlatRateCurrency extends FlatRate {

  use CommerceCurrencyResolverAmountTrait;

}
