<?php

namespace Drupal\commerce_currency_resolver;

/**
 * Defaults for resolver.
 *
 * @package Drupal\commerce_currency_resolver
 */
trait CommerceCurrencyResolverTrait {

  /**
   * Gets user country location.
   *
   * @return string
   *   2-letter country code.
   */
  public function getUserCountry() {
    $service = \Drupal::config('commerce_currency_resolver.settings')->get('currency_geo');
    return CurrencyHelper::getUserCountry($service);
  }

  /**
   * List all enabled currencies.
   *
   * @see \Drupal\commerce_currency_resolver\CurrencyHelper::getEnabledCurrency
   */
  public function getEnabledCurrencies() {
    return CurrencyHelper::getEnabledCurrency();
  }

  /**
   * Get how currency is mapped in the system. By country, language, cookie.
   *
   * @return string
   *   Return mapping type.
   *
   * @see commerce_currency_resolver.settings
   */
  public function getSourceType() {
    return \Drupal::config('commerce_currency_resolver.settings')->get('currency_mapping');
  }

  /**
   * Get how currency is added and calculated, automatic, by field.
   *
   * @return string
   *   Return source for calculating prices.
   *
   * @see commerce_currency_resolver.settings
   */
  public function getCurrencySource() {
    return \Drupal::config('commerce_currency_resolver.settings')->get('currency_source');
  }

  /**
   * Get default currency from current resolved store.
   *
   * @return string
   *   Return currency code.
   */
  public function defaultCurrencyCode() {
    return \Drupal::service('commerce_store.current_store')->getStore()->getDefaultCurrencyCode() ?? $this->fallbackCurrencyCode();
  }

  /**
   * Return default fallback currency from settings.
   *
   * @return string
   *   Return currency code.
   */
  public function fallbackCurrencyCode() {
    return \Drupal::config('commerce_currency_resolver.settings')->get('currency_default');
  }

}
