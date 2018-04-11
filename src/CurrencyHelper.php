<?php

namespace Drupal\commerce_currency_resolver;

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
    if (\Drupal::ModuleHandler()
        ->moduleExists('smart_ip') || \Drupal::ModuleHandler()
        ->moduleExists('geoip')) {
      $mapping['geo'] = t('By Country');
    }

    // Check if we site is multilingual.
    if (\Drupal::languageManager()->isMultilingual()) {
      $mapping['lang'] = t('By Language');
    }

    return $mapping;
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

}