<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_currency_resolver\ExchangeRateEventSubscriberBase;
use Drupal\commerce_currency_resolver\CurrencyHelper;

/**
 * Class ExchangeRateFixer.
 */
class ExchangeRateFixerPaid extends ExchangeRateEventSubscriberBase {

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
    return 'exchange_rate_fixer_paid';
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
  public function processCurrencies() {
    $exchange_rates = [];

    // Foreach enabled currency fetch others.
    $data = $this->getExternalData();

    if ($data) {
      // Get cross sync settings.
      $cross_sync = \Drupal::config('commerce_currency_resolver.currency_conversion')
        ->get('use_cross_sync');

      // Based on cross sync settings fetch and process data.
      if (!empty($cross_sync)) {
        $exchange_rates = $this->crossSyncCalculate($data['base'], $data['rates']);
      }

      // Fetch each currency.
      else {
        $exchange_rates = $this->eachCurrencyCalculate();
      }
    }

    return $exchange_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function eachCurrencyCalculate() {
    $exchange_rates = [];

    $data = $this->getExternalData();

    // Enabled currency.
    $enabled = CurrencyHelper::getEnabledCurrency();

    foreach ($enabled as $base => $name) {
      // Foreach enabled currency fetch others.
      if ($data[$base]) {
        $get_rates = $this->mapExchangeRates($data[$base]['rates'], $base);
        $exchange_rates[$base] = $get_rates[$base];
      }
    }

    return $exchange_rates;
  }

}
