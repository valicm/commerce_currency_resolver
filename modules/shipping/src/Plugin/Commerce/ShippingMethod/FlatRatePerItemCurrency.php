<?php

namespace Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\FlatRatePerItem;

class FlatRatePerItemCurrency extends FlatRatePerItem {

  use CurrencyResolverShippingTrait;

}
