<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_currency_resolver\Exception\CurrencyResolverMismatchException;
use Drupal\commerce_exchanger\Entity\ExchangeRatesInterface;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Config\ConfigFactory;

/**
 * Class PriceExchangerCalculator.
 *
 * @package Drupal\commerce_currency_resolver
 */
class PriceExchangerCalculator implements ExchangerCalculatorInterface {

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
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   Commerce price rounder.
   */
  public function __construct(ConfigFactory $config_factory, RounderInterface $rounder) {
    $this->configFactory = $config_factory;
    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public function priceConversion(Price $price, $target_currency) {

    // If provider does not exist.
    if (!$this->getExchangerId()) {
      throw new CurrencyResolverMismatchException('There is no active exchanger plugin selected');
    }

    // Get configuration file.
    $exchange_rates = $this->getExchangeRates();

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
   * Get all exchange rates.
   *
   * @return array
   *   Return exchange rates for currency resolver exchange plugin used.
   *
   * @see \Drupal\commerce_exchanger\Entity\ExchangeRates::getExchangerConfigName
   */
  public function getExchangeRates() {
    return $this->configFactory->get(ExchangeRatesInterface::COMMERCE_EXCHANGER_IMPORT . '.' . $this->getExchangerId())->get() ?? [];
  }

  /**
   * Return id of active provider.
   *
   * @return string
   *   Return provider.
   */
  public function getExchangerId() {
    return $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_exchange_rates');
  }

}
