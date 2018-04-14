<?php

namespace Drupal\commerce_currency_resolver;

/**
 * Holds a reference to the active currency, resolved on demand.
 */
interface CurrentCurrencyInterface {

  /**
   * Gets the resolved currency for the current request.
   *
   * @return string
   *   The active currency.
   */
  public function getCurrency();

}
