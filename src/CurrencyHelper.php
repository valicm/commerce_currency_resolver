<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_currency_resolver\Exception\CurrencyResolverMismatchException;
use Drupal\commerce_exchanger\Entity\ExchangeRatesInterface;
use Drupal\commerce_price\Price;

/**
 * Class CurrencyHelper.
 *
 * @package Drupal\commerce_currency_resolver
 */
class CurrencyHelper {

  /**
   * Get all available option where currency could be mapped.
   *
   * @return array
   *   Array of options available for currency mapping.
   */
  public static function getAvailableMapping() {
    $mapping = [];

    // Default store.
    $mapping['store'] = t('Store (default Commerce 2 behavior)');
    $mapping['cookie'] = t('Cookie (currency block selector)');

    // Check if exist geo based modules.
    // We support for now two of them.
    if (!empty(self::getGeoModules())) {
      $mapping['geo'] = t('By Country');
    }

    // Check if we site is multilingual.
    if (\Drupal::languageManager()->isMultilingual()) {
      $mapping['lang'] = t('By Language');
    }

    return $mapping;
  }

  /**
   * Get list of supported and enabled location modules.
   *
   * @return array
   *   Return list of enabled geo modules.
   */
  public static function getGeoModules() {
    $geo = [];

    if (\Drupal::ModuleHandler()->moduleExists('smart_ip')) {
      $geo['smart_ip'] = t('Smart IP');
    }

    if (\Drupal::ModuleHandler()->moduleExists('geoip')) {
      $geo['geoip'] = t('GeoIP');
    }

    return $geo;
  }

  /**
   * Get list of all enabled languages.
   *
   * @return array
   *   Array of languages.
   */
  public static function getAvailableLanguages() {
    $languages = \Drupal::languageManager()->getLanguages();

    $data = [];

    foreach ($languages as $key => $lang) {
      $data[$key] = $lang->getName();
    }

    return $data;
  }

  /**
   * Get list of all enabled currencies.
   *
   * @return array
   *   Array of currencies.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getEnabledCurrency() {
    $enabled_currencies = \Drupal::EntityTypeManager()
      ->getStorage('commerce_currency')
      ->loadByProperties([
        'status' => TRUE,
      ]);

    $currencies = [];

    foreach ($enabled_currencies as $key => $currency) {
      $currencies[$key] = $currency->getName();
    }

    return $currencies;
  }

  /**
   * Get user country location from contrib modules.
   *
   * @param string $service
   *   Type of geo service.
   *
   * @return mixed
   *   Return 2 letter country code.
   */
  public static function getUserCountry($service) {
    switch ($service) {
      case 'smart_ip':
        $location = \Drupal::service('smart_ip.smart_ip_location');
        $country = $location->get('countryCode');
        break;

      case 'geoip':
        $geo_locator = \Drupal::service('geoip.geolocation');
        $ip_address = \Drupal::request()->getClientIp();
        $country = $geo_locator->geolocate($ip_address);
        break;
    }

    // If geolocation fails for any specific reason (most likely on local
    // environment, use default country from Drupal.
    if (empty($country)) {
      $country = \Drupal::config('system.date')->get('country.default');
    }

    return $country;
  }

  /**
   * Currency conversion for prices.
   *
   * @param \Drupal\commerce_price\Price $price
   *   Price object.
   * @param string $currency
   *   Target currency.
   *
   * @return \Drupal\commerce_price\Price|static
   *   Return updated price object with new currency.
   */
  public static function priceConversion(Price $price, $currency) {
    $exchange_rate_source = \Drupal::config('commerce_currency_resolver.settings')->get('currency_exchange_rates');

    if (empty($exchange_rate_source)) {
      throw new CurrencyResolverMismatchException('Missing exchange rate source');
    }

    // Get exchange rates based on commerce_exchanger name formatting.
    $mapping = \Drupal::config(ExchangeRatesInterface::COMMERCE_EXCHANGER_IMPORT . '.' . $exchange_rate_source)->getRawData();

    // Current currency.
    $current_currency = $price->getCurrencyCode();

    // Determine rate.
    $rate = NULL;
    if (isset($mapping[$current_currency][$currency])) {
      $rate = $mapping[$current_currency][$currency]['value'];
    }

    // Fallback to use 1 as rate.
    if (empty($rate)) {
      $rate = '1';
    }

    // Convert. Convert rate to string.
    $price = $price->convert($currency, (string) $rate);
    $rounder = \Drupal::service('commerce_price.rounder');
    $price = $rounder->round($price);
    return $price;
  }

}
