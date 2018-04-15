<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_currency_resolver\Event\CommerceCurrencyResolverEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use GuzzleHttp\Exception\RequestException;
use SimpleXMLElement;
use Drupal\commerce_currency_resolver\CurrencyHelper;

/**
 * Class EuropeanCentralBank.
 */
class EuropeanCentralBank implements EventSubscriberInterface {

  /**
   * ECB daily XML url.
   */
  const API_URL = 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

  /**
   * Fetch external data.
   */
  public function getExternalData() {
    $data = NULL;

    // Prepare for client.
    $client = \Drupal::httpClient();
    $url = self::API_URL;
    $method = 'GET';
    $options = [];

    try {
      $response = $client->request($method, $url, $options);
      // Expected result.
      $xml = $response->getBody()->getContents();
      $data = $this->parseExternalData($xml);
    }
    catch (RequestException $e) {
      \Drupal::logger('commerce_currency_resolver')->debug($e->getMessage());
    }

    return $data;
  }

  /**
   * Parse the xml.
   */
  private function parseExternalData($raw_xml) {
    try {
      $xml = new SimpleXMLElement($raw_xml);
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
   * This method is called whenever the import event is dispatched.
   */
  public function import() {
    $data = $this->getExternalData();

    if ($data) {

      // Get existing settings.
      $settings = \Drupal::config('commerce_currency_resolver.currency_conversion');

      // Get cross sync settings.
      $cross_sync = $settings->get('use_cross_sync');

      // Get current mapping.
      $mapping = $settings->get('exchange');

      // Default currency.
      $currency_default = \Drupal::config('commerce_currency_resolver.settings')
        ->get('currency_default');

      $exchange_rates = [];
      if (!empty($cross_sync)) {
        // ECB uses EUR as base currency.
        // If euro is not main currence we need recaclulate.
        if ($currency_default != 'EUR') {
          $data = CurrencyHelper::reverseCalculate($currency_default, 'EUR', $data);
        }

        $exchange_rates[$currency_default] = [];
        foreach ($data as $currency => $rate) {
          if (empty($mapping[$currency_default][$currency]['sync'][1])) {
            $exchange_rates[$currency_default][$currency]['value'] = $rate;
            $exchange_rates[$currency_default][$currency]['sync'] = $mapping[$currency_default][$currency]['sync'];
          }

          else {
            $exchange_rates[$currency_default][$currency] = $mapping[$currency_default][$currency];
          }
        }
      }

      // Get config.
      $config = \Drupal::service('config.factory')->getEditable('commerce_currency_resolver.currency_conversion');

      // Set and save new message value.
      $config->set('exchange', $exchange_rates)->save();
    }
  }

}
