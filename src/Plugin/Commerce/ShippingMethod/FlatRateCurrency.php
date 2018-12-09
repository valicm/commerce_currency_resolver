<?php

namespace Drupal\commerce_currency_resolver\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\FlatRate as ShippingFlateRate;
use Drupal\commerce_shipping\ShippingRate;

/**
 * Provides the Multicurrency FlatRate shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "flat_rate",
 *   label = @Translation("Flat rate"),
 * )
 */
class FlatRateCurrency extends ShippingFlateRate {

  use CommerceCurrencyResolverAmountTrait;

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Rate IDs aren't used in a flat rate scenario because there's always a
    // single rate per plugin, and there's no support for purchasing rates.
    $rate_id = 0;
    $amount = $this->configuration['rate_amount'];

    $amount = new Price($amount['number'], $amount['currency_code']);

    // Check if is enabled multicurrency.
    if ($this->shouldCurrencyRefresh()) {
      // If current currency does not match to shipment code.
      if ($this->currentCurrency() !== $amount->getCurrencyCode()) {
        $amount = $this->getPrice($amount, $this->currentCurrency());
      }
    }

    $rates = [];
    $rates[] = new ShippingRate($rate_id, $this->services['default'], $amount);

    return $rates;
  }

}
