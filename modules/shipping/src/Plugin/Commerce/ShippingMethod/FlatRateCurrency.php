<?php

namespace Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\FlatRate;

class FlatRateCurrency extends FlatRate {

  use CurrencyResolverShippingTrait;

}
