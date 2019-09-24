<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_currency_resolver\Exception\CurrencyResolverMismatchException;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class PriceExchangerCalculator.
 *
 * @package Drupal\commerce_currency_resolver
 */
class PriceExchangerCalculator implements ExchangerCalculatorInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Exchange rate provider.
   *
   * @var \Drupal\commerce_exchanger\Entity\ExchangeRatesInterface
   */
  protected $provider;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Commerce price rounder service.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * PriceExchangerCalculator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   Commerce price rounder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory, RounderInterface $rounder) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->provider = $entity_type_manager->getStorage('commerce_exchange_rates')->load($this->getExchangerId());
    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public function priceConversion(Price $price, $target_currency) {

    // If provider does not exist.
    if (!$this->provider) {
      throw new CurrencyResolverMismatchException('There is no exchange rate plugin');
    }

    // Get configuration file.
    $exchange_rates = $this->configFactory->get($this->provider->getExchangerConfigName())->get();

    // Current currency.
    $price_currency = $price->getCurrencyCode();

    // Get rate or fallback to 1.
    $rate = $exchange_rates[$price_currency][$target_currency]['value'] ?? '1';

    // Convert. Convert rate to string.
    $price = $price->convert($target_currency, (string) $rate);
    $price = $this->rounder->round($price);
    return $price;
  }

  /**
   * Return id of active provider.
   *
   * @return string
   *   Return provider.
   */
  protected function getExchangerId() {
    return $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_exchange_rates');
  }

}
