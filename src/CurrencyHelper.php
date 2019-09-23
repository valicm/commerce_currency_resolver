<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Site\Settings;
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
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * CurrencyHelper constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager, LanguageManagerInterface $languageManager, ModuleHandlerInterface $module_handler, CurrentStoreInterface $current_store) {
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->moduleHandler = $module_handler;
    $this->currentStore = $current_store;
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
  public function getExchangeRatesProviders() {
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
   * @return string
   */
  public function currentLanguage() {
    return $this->languageManager->getCurrentLanguage()->getId();
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

  /**
   * Get how currency is mapped in the system. By country, language, cookie.
   *
   * @return string
   *   Return mapping type.
   *
   * @see commerce_currency_resolver.settings
   */
  public function getSourceType() {
    return $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_mapping');
  }

  /**
   * Get how currency is mapped in the system. By country, language, cookie.
   *
   * @return string
   *   Return mapping type.
   *
   * @see commerce_currency_resolver.settings
   */
  public function getCurrencyMappingMatrix() {
    return $this->configFactory->get('commerce_currency_resolver.currency_mapping')->get('matrix');
  }

  public function getDomicileCurrency() {
    return $this->configFactory->get('commerce_currency_resolver.currency_mapping')->get('domicile_currency');
  }

  /**
   * Get default currency from current resolved store.
   *
   * @return string
   *   Return currency code.
   */
  public function defaultCurrencyCode() {
    if ($store = $this->currentStore->getStore()) {
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
    return $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_default');
  }

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

}
