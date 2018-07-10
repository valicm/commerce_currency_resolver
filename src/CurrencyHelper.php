<?php

namespace Drupal\commerce_currency_resolver;

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

    // Default none.
    $mapping['0'] = t('Cookie (currency block selector)');

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
   * Get all Exchange rates services.
   *
   * @return array
   *   Return array of available services.
   */
  public static function getExchangeServices() {
    $container = \Drupal::getContainer();
    $kernel = $container->get('kernel');

    // Get all services.
    $services = $kernel->getCachedContainerDefinition()['services'];

    $options = [];
    foreach ($services as $service_id => $value) {
      // Split service id. Service could be in any module added, so we
      // need to split service_id to rid of module name.
      $name = explode('.', $service_id);

      // Get second part of service name and check for specific string on
      // the beginning.
      if (isset($name[1]) && substr($name[1], 0, 13) === 'exchange_rate') {
        $clean_name = str_replace('exchange_rate_', '', $name[1]);
        $options[$name[1]] = str_replace('_', ' ', $clean_name);
      }
    }

    return $options;
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
    // Get currency conversion settings.
    $config = \Drupal::config('commerce_currency_resolver.currency_conversion');

    // Get specific settings.
    $mapping = $config->get('exchange');

    // Current currency.
    $current_currency = $price->getCurrencyCode();

    // Determine rate.
    $rate = $mapping[$current_currency][$currency]['value'];

    // Fallback to use 1 as rate.
    if (empty($rate)) {
      $rate = '1';
    }

    // Convert. Convert rate to string.
    $price = $price->convert($currency, (string) $rate);
    return $price;
  }

  /**
   * Conversion for currencies when we use cross sync conversion.
   *
   * @param string $current
   *   Current currency.
   * @param string $target
   *   Target currency.
   *
   * @return float|int
   *   Return rate for conversion calculation.
   *
   * @todo Remove in never version. Deprecated.
   */
  public static function crossSyncConversion($current, $target) {
    $currency_default = \Drupal::config('commerce_currency_resolver.settings')
      ->get('currency_default');

    $mapping = \Drupal::config('commerce_currency_resolver.currency_conversion')
      ->get('exchange');

    if ($current === $currency_default) {
      $rate = $mapping[$currency_default][$target]['value'];
    }

    elseif ($target === $currency_default) {
      $rate = 1 / $mapping[$currency_default][$current]['value'];
    }

    else {
      $current_rate = $mapping[$currency_default][$current]['value'];
      $target_rate = $mapping[$currency_default][$target]['value'];
      $rate = $target_rate / $current_rate;
    }

    return $rate;
  }

}
