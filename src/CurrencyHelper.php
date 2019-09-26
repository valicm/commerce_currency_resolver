<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper for various parts of the module.
 */
class CurrencyHelper implements CurrencyHelperInterface {

  use StringTranslationTrait;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Core module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * Route match definition.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * CurrencyHelper constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Core language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Core module handler.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   Current resolved Commerce store.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager, LanguageManagerInterface $languageManager, ModuleHandlerInterface $module_handler, CurrentStoreInterface $current_store, RouteMatchInterface $route_match, PathMatcherInterface $path_matcher) {
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->moduleHandler = $module_handler;
    $this->currentStore = $current_store;
    $this->routeMatch = $route_match;
    $this->pathMatcher = $path_matcher;
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
    $providers = $this->entityTypeManager->getStorage('commerce_exchange_rates')->loadMultiple();

    $exchange_rates = [];
    foreach ($providers as $provider) {
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
   * {@inheritdoc}
   */
  public function currentLanguage() {
    return $this->languageManager->getCurrentLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getGeoModules() {
    $geo = [];

    if ($this->moduleHandler->moduleExists('smart_ip')) {
      $geo['smart_ip'] = $this->t('Smart IP');
    }

    if ($this->moduleHandler->moduleExists('geoip')) {
      $geo['geoip'] = $this->t('GeoIP');
    }

    return $geo;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getSourceType() {
    return $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_mapping');
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingMatrix() {
    return $this->configFactory->get('commerce_currency_resolver.currency_mapping')->get('matrix');
  }

  /**
   * {@inheritdoc}
   */
  public function getDomicileCurrency() {
    return $this->configFactory->get('commerce_currency_resolver.currency_mapping')->get('domicile_currency');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultCurrencyCode() {
    if ($store = $this->currentStore->getStore()) {
      return $store->getDefaultCurrencyCode();
    }

    return $this->fallbackCurrencyCode();
  }

  /**
   * {@inheritdoc}
   */
  public function fallbackCurrencyCode() {
    return $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_default');
  }

  /**
   * {@inheritdoc}
   */
  public function getCookieName() {
    $cookieName = &drupal_static(__FUNCTION__);
    if (!isset($cookieName)) {
      $cookieName = Settings::get('commerce_currency_cookie') ?? 'commerce_currency';
    }
    return $cookieName;
  }

  /**
   * {@inheritdoc}
   */
  public function isAdminOrder() {
    if ($this->adminOrderRoutes() && $this->isAdminPath()) {
      return $this->adminOrderRoutes();
    }

    return NULL;
  }

  protected function adminOrderRoutes() {
    if ($order_id = $this->routeMatch->getParameter('commerce_order')) {
      if ($order = Order::load($order_id)) {
        return $order->getTotalPrice()->getCurrencyCode();
      }
    }

    return NULL;
  }

  /**
   * @return bool
   */
  protected function isAdminPath() {
    $path = $this->requestStack->getCurrentRequest()->getPathInfo();
    // Compare the lowercase path alias (if any) and internal path.
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');

    $patterns = '/admin/*';

    return $this->pathMatcher->matchPath($path, $patterns);
  }

}
