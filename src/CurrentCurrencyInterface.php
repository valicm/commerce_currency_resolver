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

  /**
   * Gets user country location.
   *
   * @param string $service
   *   Geolocation service used on site.
   *
   * @return string
   *   2-letter country code.
   */
  public function getUserCountry($service);

  /**
   * List all enabled currencies.
   *
   * @return string
   *   Return array of all enabled currencies.
   */
  public function getEnabledCurrencies();

}
