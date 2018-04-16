<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_currency_resolver\Event\ExchangeImport;

/**
 * Provides an interface for Exchange data sources.
 *
 * @package Drupal\commerce_currency_resolver
 */
interface ExchangeRateDataSourceInterface {

  /**
   * Exchange rate API url.
   *
   * @return string
   *   Return URL to remote exchange service.
   */
  public static function apiUrl();

  /**
   * Exchange service API key.
   *
   * @return string
   *   Return api key for remote exchange service.
   */
  public static function apiKey();

  /**
   * Generic client to make request. Utilizing Drupal::httpClient.
   *
   * @param string $method
   *   Method for request.
   * @param string $url
   *   URL upon request is made.
   * @param array $options
   *   Additional option for request.
   *
   * @return mixed
   *   Return response.
   */
  public function apiClient($method, $url, array $options);

  /**
   * Exchange rate Event Subscriber source ID.
   *
   * @return string
   *   Return machine name of event subscriber.
   */
  public static function sourceId();

  /**
   * Fetch external data.
   *
   * @param string $base_currency
   *   If we fetch data based on specific currency.
   */
  public function getExternalData($base_currency = NULL);

  /**
   * Helper function to create array for exchange rates.
   *
   * @param array $data
   *   New fetched data, array format: currency_code => rate.
   * @param string $base_currency
   *   Parent currency upon we build array.
   *
   * @return array
   *   Return array prepared for saving in Drupal config.
   */
  public function mapExchangeRates(array $data, $base_currency);

  /**
   * Recalculate currencies from exchange rate between two other currencies.
   *
   * @param string $target_currency
   *   Currency to which should be exchange rate calculated.
   * @param string $base_currency
   *   Base currency upon which we have exchange rates.
   * @param array $data
   *   Currency and rate array.
   *
   * @return array
   *   Return recalculated data.
   */
  public function reverseCalculate($target_currency, $base_currency, array $data);

  /**
   * Save exchanges rates to config. Update last cron update for import.
   *
   * @param array $exchange_rates
   *   Array prepared for saving.
   */
  public function saveExchangeRatesConfig(array $exchange_rates);

  /**
   * Import only currency rates for default currency.
   *
   * @return array
   *   Return prepared data for saving.
   */
  public function processDefaultCurrency();

  /**
   * Process all currencies for rates for other currencies.
   *
   * @return array
   *   Return prepared data for saving.
   */
  public function processAllCurrencies();

  /**
   * This method is called whenever the import event is dispatched.
   *
   * @param \Drupal\commerce_currency_resolver\event\ExchangeImport $event
   *   Event which is triggered.
   */
  public function import(ExchangeImport $event);
}