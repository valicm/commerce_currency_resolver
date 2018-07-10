<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_currency_resolver\ExchangeRateEventSubscriberBase;
use SimpleXMLElement;

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
  public function processCurrencies() {
    // ECB have only one currency. With this issue
    // https://www.drupal.org/project/commerce_currency_resolver/issues/2984828
    // we have only one import.
    $exchange_rates = [];
    $data = $this->getExternalData();

    if ($data) {
      // ECB uses only EUR as base currency, so we need to
      // recalculate other currencies.
      $exchange_rates = $this->crossSyncCalculate('EUR', $data);
    }

    return $exchange_rates;
  }

}
