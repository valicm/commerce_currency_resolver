<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper for various parts of the module.
 */
class CurrencyHelper implements CurrencyHelperInterface {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * CurrencyHelper constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager, LanguageManagerInterface $languageManager, ModuleHandlerInterface $module_handler) {
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrencies() {
    /** @var \Drupal\commerce_price\Entity\CurrencyInterface[] $currencies */
    $currencies = $this->entityTypeManager->getStorage('commerce_currency')->loadMultiple();

    // Set defaults.
    $active_currencies = [];

    foreach ($currencies as $currency) {
      if ($currency->status()) {
        $active_currencies[$currency->getCurrencyCode()] = $currency->getName();
      }
    }

    return $active_currencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getExchangeRates() {
    /** @var \Drupal\commerce_exchanger\Entity\ExchangeRatesInterface[] $providers */
    $providers =  $this->entityTypeManager->getStorage('commerce_exchange_rates')->loadMultiple();

    $exchange_rates = [];
    foreach ($providers as $id => $provider) {
      if ($provider->status()) {
        $exchange_rates[$provider->id()] = $provider->label();
      }
    }

    return $exchange_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguages() {
    $languages = $this->languageManager->getLanguages();

    $data = [];

    foreach ($languages as $key => $lang) {
      $data[$key] = $lang->getName();
    }

    return $data;
  }

  /**
   * Get list of supported and enabled location modules.
   *
   * @return array
   *   Return list of enabled geo modules.
   */
  public function getGeoModules() {
    $geo = [];

    if ($this->moduleHandler->moduleExists('smart_ip')) {
      $geo['smart_ip'] = t('Smart IP');
    }

    if ($this->moduleHandler->moduleExists('geoip')) {
      $geo['geoip'] = t('GeoIP');
    }

    return $geo;
  }

  /**
   * Get user country location from contrib modules.
   *
   * @return mixed
   *   Return 2 letter country code.
   */
  public function getUserCountry() {
    $service = $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_geo');

    switch ($service) {
      case 'smart_ip':
        $country = \Drupal::service('smart_ip.smart_ip_location')->get('countryCode');
        break;

      case 'geoip':
        $ip_address = $this->requestStack->getCurrentRequest()->getClientIp();
        $country = \Drupal::service('geoip.geolocation')->geolocate($ip_address);
        break;
    }

    // If geolocation fails for any specific reason (most likely on local
    // environment, use default country from Drupal.
    if (empty($country)) {
      $country = $this->configFactory->get('system.date')->get('country.default');
    }

    return $country;
  }

}
