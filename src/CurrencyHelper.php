<?php

namespace Drupal\commerce_currency_resolver;

use CommerceGuys\Intl\Currency\CurrencyRepository;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class CurrencyHelper.
 *
 * @package Drupal\commerce_currency_resolver
 */
class CurrencyHelper {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface;
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get all available option where currency could be mapped.
   *
   * @return array
   *   Array of options available for currency mapping.
   */
  public static function getAvailableMapping() {
    $mapping = [];

    // Default none.
    $mapping['0'] = t('None');

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

    return $country;
  }

}