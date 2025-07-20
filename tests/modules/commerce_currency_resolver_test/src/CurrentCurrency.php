<?php

namespace Drupal\commerce_currency_resolver_test;

use Drupal\commerce_currency_resolver\CurrentCurrency as CoreCurrentCurrency;

/**
 * Holds a reference to the currency, resolved on demand.
 */
class CurrentCurrency extends CoreCurrentCurrency {

  /**
   * {@inheritdoc}
   */
  public function getCurrency() {
    return 'VUV';
  }

}
