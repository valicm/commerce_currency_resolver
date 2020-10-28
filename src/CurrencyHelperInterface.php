<?php

namespace Drupal\commerce_currency_resolver;

/**
 * Interface CurrencyHelperInterface.
 *
 * @package Drupal\commerce_currency_resolver
 */
interface CurrencyHelperInterface {

  /**
   * Return formatted array of available exchange rates plugins.
   *
   * @return array
   *   List of keyed providers ['provider_id' => 'Provider name'].
   */
  public function getExchangeRatesProviders();

  /**
   * Return formatted array of available currencies.
   *
   * @return array
   *   List of keyed currencies ['HRK' => 'Croatian Kuna'].
   */
  public function getCurrencies();

  /**
   * Return formatted array of languages.
   *
   * @return array
   *   List of keyed languages ['HR' => 'Croatian'].
   */
  public function getLanguages();

  /**
   * Return current user language.
   *
   * @return string
   *   Two letter language code.
   */
  public function currentLanguage();

  /**
   * Get list of enabled geo modules if any.
   *
   * @return array
   *   List of geo modules.
   */
  public function getGeoModules();

  /**
   * Get user country location from contrib modules.
   *
   * @return mixed
   *   Return 2 letter country code.
   */
  public function getUserCountry();

  /**
   * Get how currency is mapped in the system. By country, language, cookie.
   *
   * @return string
   *   Return mapping type.
   *
   * @see commerce_currency_resolver.settings
   */
  public function getSourceType();

  /**
   * Get how currency is mapped in the system. By country, language, cookie.
   *
   * @return array
   *   Return mapping type.
   *
   * @see commerce_currency_resolver.settings
   */
  public function getMappingMatrix();

  /**
   * Return if domicile currency is used.
   *
   * @return mixed
   *   Return if is active.
   */
  public function getDomicileCurrency();

  /**
   * Get default currency from current resolved store.
   *
   * @return string
   *   Return currency code.
   */
  public function defaultCurrencyCode();

  /**
   * Return default fallback currency from settings.
   *
   * @return string
   *   Return currency code.
   */
  public function fallbackCurrencyCode();

  /**
   * Get cookie name.
   *
   * @return string
   *   Return cookie name.
   */
  public function getCookieName();

}
