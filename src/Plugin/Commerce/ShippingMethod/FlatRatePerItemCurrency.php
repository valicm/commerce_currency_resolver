<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRate;

/**
 * Provides the FlatRatePerItem shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "flat_rate_per_item",
 *   label = @Translation("Flat rate per item"),
 * )
 */
class FlatRatePerItemCurrency extends FlatRateCurrency {

  use CommerceCurrencyResolverAmountTrait;

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Nothing to do. Go to parent.
    if (!$this->shouldCurrencyRefresh($this->configuration['rate_amount']['currency_code'])) {
      return parent::calculateRates($shipment);
    }

    $quantity = 0;
    foreach ($shipment->getItems() as $shipment_item) {
      $quantity += $shipment_item->getQuantity();
    }
    // Rate IDs aren't used in a flat rate scenario because there's always a
    // single rate per plugin, and there's no support for purchasing rates.
    $rate_id = 0;
    $amount = $this->configuration['rate_amount'];
    $amount = new Price($amount['number'], $amount['currency_code']);
    // Get resolved price from original price.
    $amount = $this->getPrice($amount);

    $amount = $amount->multiply((string) $quantity);
    $rates = [];
    $rates[] = new ShippingRate($rate_id, $this->services['default'], $amount);

    return $rates;
  }

}
