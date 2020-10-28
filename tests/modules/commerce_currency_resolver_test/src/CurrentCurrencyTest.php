<?php

namespace Drupal\commerce_currency_resolver_test;

use Drupal\commerce_currency_resolver\CurrentCurrency;

/**
 * Holds a reference to the currency, resolved on demand.
 */
class CurrentCurrencyTest extends CurrentCurrency {

  /**
   * {@inheritdoc}
   */
  public function getCurrency() {
    return 'VUV';
  }

}
