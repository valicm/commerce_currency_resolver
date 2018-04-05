<?php
namespace Drupal\commerce_currency_resolver\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Resolver\PriceResolverInterface;

/**
 * Returns a price and currency depending of language.
 */
class CommerceCurrencyResolver implements PriceResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {

    // Define mapping between country and currency.
    $currency_by_location = ['HR' => 'USD', 'FR' => 'EUR', 'UK' => 'JPY'];

    // Get location.
    $location = \Drupal::service('smart_ip.smart_ip_location');
    $country = $location->get('countryCode');

    $currency = 'USD';


    if (isset($currency_by_location[$country])) {
      $currency = $currency_by_location[$country];
    }

    // Get value from currency price field.
    if ($entity->hasField('field_price_' . strtolower($currency))) {
      $price = $entity->get('field_price_' . strtolower($currency))->getValue();
      $price = reset($price);
      return new Price($price['number'], $price['currency_code']);
    }
  }
}
