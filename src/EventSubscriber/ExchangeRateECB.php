<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_currency_resolver\ExchangeRateEventSubscriberBase;
use SimpleXMLElement;
use Drupal\commerce_currency_resolver\CurrencyHelper;

/**
 * Class ExchangeRateECB.
 */
class ExchangeRateECB extends ExchangeRateEventSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function apiUrl() {
    return 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
  }

  /**
   * {@inheritdoc}
   */
  public static function sourceId() {
    return 'exchange_rate_ecb';
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalData($base_currency = NULL) {
    $data = NULL;

    // Prepare for client.
    $url = self::apiUrl();
    $method = 'GET';
    $options = [];

    $request = $this->apiClient($method, $url, $options);

    if ($request) {

      try {
        $xml = new SimpleXMLElement($request);
      }
      catch (\Exception $e) {
        \Drupal::logger('commerce_currency_resolver')->debug($e->getMessage());
      }

      $data = [];

      // Loop and build array.
      foreach ($xml->Cube->Cube->Cube as $rate) {
        $code = (string) $rate['currency'];
        $rate = (string) $rate['rate'];
        $data[$code] = $rate;
      }

    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefaultCurrency() {
    $exchange_rates = [];
    $data = $this->getExternalData();

    if ($data) {
      // Default currency.
      $currency_default = \Drupal::config('commerce_currency_resolver.settings')
        ->get('currency_default');

      // ECB uses EUR as base currency.
      // If euro is not main currence we need recalculate.
      if ($currency_default != 'EUR') {
        $data = $this->reverseCalculate($currency_default, 'EUR', $data);
      }

      // Prepare data for saving.
      $exchange_rates = $this->mapExchangeRates($data, $currency_default);
    }

    return $exchange_rates;

  }

  /**
   * {@inheritdoc}
   */
  public function processAllCurrencies() {
    $data = $this->getExternalData();

    if ($data) {

      // Default currency.
      $currency_default = \Drupal::config('commerce_currency_resolver.settings')
        ->get('currency_default');

      // Enabled currency.
      $enabled = CurrencyHelper::getEnabledCurrency();

      // For each currency built data.
      foreach ($enabled as $currency_code => $value) {
        // ECB uses only EUR as base currency, so we need to
        // recalculate other currencies.
        $recalculate = $this->reverseCalculate($currency_code, 'EUR', $data);

        // Prepare data.
        $get_rates = $this->mapExchangeRates($recalculate, $currency_code);
        $exchange_rates[$currency_code] = $get_rates[$currency_code];
      }
    }
  }

}
