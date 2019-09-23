<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Site\Settings;

/**
 * Defaults for resolver.
 *
 * @package Drupal\commerce_currency_resolver
 */
trait CommerceCurrencyResolverTrait {

  /**
   * Get cookie name.
   */
  public function getCookieName() {
    $cookieName = &drupal_static(__FUNCTION__);
    if (!isset($cookieName)) {
      $cookieName = Settings::get('commerce_currency_cookie') ?? 'commerce_currency';
    }
    return $cookieName;
  }

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
   */
  public function getEnabledCurrencies() {
    return \Drupal::service('commerce_currency_resolver.currency_helper')->getCurrencies();
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
    /** @var StoreInterface $store */
    if ($store = \Drupal::service('commerce_store.current_store')->getStore()) {
      return $store->getDefaultCurrencyCode();
    }

    return $this->fallbackCurrencyCode();
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
