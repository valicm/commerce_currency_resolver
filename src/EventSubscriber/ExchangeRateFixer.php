<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_currency_resolver\ExchangeRateEventSubscriberBase;
use Drupal\commerce_currency_resolver\CurrencyHelper;

/**
 * Class ExchangeRateFixer.
 */
class ExchangeRateFixer extends ExchangeRateEventSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function apiUrl() {
    return 'http://data.fixer.io/api/latest';
  }

  /**
   * {@inheritdoc}
   */
  public static function sourceId() {
    return 'exchange_rate_fixer';
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalData($base_currency = NULL) {
    $data = NULL;

    // Prepare for client.
    $url = self::apiUrl();
    $method = 'GET';
    $options = [
      'query' => ['access_key' => self::apiKey()],
    ];

    // Add base currency.
    if (!empty($base_currency)) {
      $options['query']['base'] = $base_currency;
    }

    $request = $this->apiClient($method, $url, $options);

    if ($request) {
      $json = json_decode($request);

      if ($json->success) {
        // Leave base currency. In some cases we don't know base currency.
        // Fixer.io on free plan uses your address for base currency, and in
        // Drupal you could have different default value.
        $data['base'] = $json->base;

        // Loop and build array.
        foreach ($json->rates as $key => $value) {
          $data['rates'][$key] = $value;
        }

      }

      else {
        \Drupal::logger('commerce_currency_resolver')->debug($json->error->info);
        return FALSE;
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefaultCurrency() {
    $exchange_rates = [];

    // Default currency.
    $currency_default = \Drupal::config('commerce_currency_resolver.settings')
      ->get('currency_default');

    $data = $this->getExternalData();

    if ($data) {
      // Currency is tied to your account on Fixer.io, so we need to be sure
      // to get correct data. You cannot choose base currency on free
      // account.
      $process = $this->reverseCalculate($currency_default, $data['base'], $data['rates']);

      // Get data.
      $exchange_rates = $this->mapExchangeRates($process, $currency_default);
    }

    return $exchange_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function processAllCurrencies() {
    $exchange_rates = [];

    // Enabled currency.
    $enabled = CurrencyHelper::getEnabledCurrency();

    foreach ($enabled as $base => $name) {
      // Foreach enabled currency fetch others.
      $data = $this->getExternalData($base);

      if ($data) {
        $get_rates = $this->mapExchangeRates($data['rates'], $base);
        $exchange_rates[$base] = $get_rates[$base];
      }
    }

    return $exchange_rates;
  }

}
