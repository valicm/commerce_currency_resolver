<?php

namespace Drupal\commerce_currency_resolver;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_currency_resolver\Event\CommerceCurrencyResolverEvents;
use Drupal\commerce_currency_resolver\Event\ExchangeImport;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides a base class for Exchange rates sources.
 */
abstract class ExchangeRateEventSubscriberBase implements EventSubscriberInterface, ExchangeRateDataSourceInterface {

  /**
   * {@inheritdoc}
   */
  public static function apiKey() {
    return \Drupal::config('commerce_currency_resolver.currency_conversion')->get('api_key');
  }

  /**
   * {@inheritdoc}
   */
  public function apiClient($method, $url, array $options) {
    $data = FALSE;

    // Prepare for client.
    $client = \Drupal::httpClient();

    try {
      $response = $client->request($method, $url, $options);

      // Expected result.
      $data = $response->getBody()->getContents();

    }
    catch (RequestException $e) {
      \Drupal::logger('commerce_currency_resolver')->debug($e->getMessage());
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CommerceCurrencyResolverEvents::IMPORT] = ['import'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function mapExchangeRates(array $data, $base_currency) {

    // Get current exchange rates.
    $mapping = \Drupal::config('commerce_currency_resolver.currency_conversion')
      ->get('exchange');

    // Set defaults.
    $exchange_rates = [];
    $exchange_rates[$base_currency] = [];

    // Loop trough data, set new values or leave manually defined.
    foreach ($data as $currency => $rate) {
      if (empty($mapping[$base_currency][$currency]['sync'][1])) {
        $exchange_rates[$base_currency][$currency]['value'] = $rate;
        $sync_settings = isset($mapping[$base_currency][$currency]['sync']) ? $mapping[$base_currency][$currency]['sync'] : [1 => 0];
        $exchange_rates[$base_currency][$currency]['sync'] = $sync_settings;
      }

      else {
        $exchange_rates[$base_currency][$currency] = $mapping[$base_currency][$currency];
      }
    }

    return $exchange_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseCalculate($target_currency, $base_currency, array $data) {

    // Get all enabled currencies.
    $enabled = CurrencyHelper::getEnabledCurrency();

    // If we accidentally sent same target and base currency.
    $rate_target_currency = !empty($data[$target_currency]) ? $data[$target_currency] : 1;

    // Get rate based from base currency.
    $currency_default = 1 / $rate_target_currency;

    $recalculated = [];
    $recalculated[$base_currency] = $currency_default;

    // Recalculate all data.
    foreach ($data as $currency => $rate) {
      if ($currency != $target_currency && isset($enabled[$currency])) {
        $recalculated[$currency] = $rate * $currency_default;
      }
    }

    return $recalculated;
  }

  /**
   * {@inheritdoc}
   */
  public function crossSyncCalculate($base_currency, array $data) {
    $exchange_rates = [];

    // Enabled currency.
    $enabled = CurrencyHelper::getEnabledCurrency();

    if ($data) {
      foreach ($enabled as $currency_code => $name) {
        $recalculate = $this->reverseCalculate($currency_code, $base_currency, $data);

        // Prepare data.
        $get_rates = $this->mapExchangeRates($recalculate, $currency_code);
        $exchange_rates[$currency_code] = $get_rates[$currency_code];
      }
    }

    return $exchange_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function saveExchangeRatesConfig(array $exchange_rates) {
    // Get config.
    $config = \Drupal::service('config.factory')
      ->getEditable('commerce_currency_resolver.currency_conversion');

    // Set and save new message value.
    $config->set('exchange', $exchange_rates)->save();

    // Set time when cron is done.
    \Drupal::state()
      ->set('commerce_currency_resolver.last_update_time', REQUEST_TIME);
  }

  /**
   * {@inheritdoc}
   */
  public function import(ExchangeImport $event) {

    // Check if we trigger correct EventSubscriber.
    if ($event->getLabel() === $this->sourceId()) {

      $exchange_rates = $this->processCurrencies();

      // Write new data.
      if (!empty($exchange_rates)) {
        $this->saveExchangeRatesConfig($exchange_rates);
      }
    }

  }

}
