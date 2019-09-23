<?php

namespace Drupal\commerce_currency_resolver;

/**
 * Interface CurrencyHelperInterface
 *
 * @package Drupal\commerce_currency_resolver
 */
interface CurrencyHelperInterface {

  /**
   * Return formatted array of available exchange rates plugins.
   *
   * @return array
   */
  public function getExchangeRates();

  /**
   * Return formatted array of available currencies.
   *
   * @return array
   */
  public function getCurrencies();

  /**
   * Return formatted array of languages.
   *
   * @return array
   */
  public function getLanguages();

}
