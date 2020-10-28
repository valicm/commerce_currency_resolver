<?php

namespace Drupal\commerce_currency_resolver_shipping\EventSubscriber;

use Drupal\commerce_currency_resolver\CommerceCurrencyResolversRefreshTrait;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Event\ShippingRatesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_currency_resolver\CurrentCurrency;

/**
 * Handling shipments rates.
 *
 * @package Drupal\commerce_currency_resolver\EventSubscriber
 */
class CommerceShippingCurrency implements EventSubscriberInterface {

  use CommerceCurrencyResolversRefreshTrait;

  /**
   * Current currency.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * Exchanger calculator.
   *
   * @var \Drupal\commerce_exchanger\ExchangerCalculatorInterface
   */
  protected $priceExchanger;

  /**
   * {@inheritdoc}
   */
  public function __construct(CurrentCurrency $currency, ExchangerCalculatorInterface $price_exchanger) {
    $this->currentCurrency = $currency;
    $this->priceExchanger = $price_exchanger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_shipping.rates' => 'shippingCurrency',
    ];
    return $events;
  }

  /**
   * Calculate rates based on current currency.
   *
   * @param \Drupal\commerce_shipping\Event\ShippingRatesEvent $event
   *   The order event.
   */
  public function shippingCurrency(ShippingRatesEvent $event) {
    /** @var \Drupal\commerce_shipping\ShippingRate[] $rates */
    if ($rates = $event->getRates()) {
      if ($this->isShippingAdminRoute()) {
        $resolved_currency = $event->getShipment()->getAmount()->getCurrencyCode();
      } else {
        $resolved_currency = $this->currentCurrency->getCurrency();
      }
      $shipping_conversion = [];

      foreach ($rates as $key => $rate) {
        if ($rate->getAmount()->getCurrencyCode() != $resolved_currency) {
          $plugin_data = $event->getShippingMethod()->getPlugin()->getConfiguration();

          // Check if they are entered separate prices per currencies.
          if (isset($plugin_data['fields'][$resolved_currency]) && is_numeric($plugin_data['fields'][$resolved_currency]['number'])) {
            $resolved_price = new Price((string) $plugin_data['fields'][$resolved_currency]['number'], $plugin_data['fields'][$resolved_currency]['currency_code']);
            $rate->setOriginalAmount($resolved_price);
            $rate->setAmount($resolved_price);
          }

          // Otherwise just calculate automatically.
          else {
            $rate->setAmount($this->priceExchanger->priceConversion($rate->getAmount(), $resolved_currency));
            $rate->setOriginalAmount($this->priceExchanger->priceConversion($rate->getOriginalAmount(), $resolved_currency));
          }

          $shipping_conversion[$key] = $rate;
        }
      }

      if ($shipping_conversion) {
        $event->setRates($shipping_conversion);
      }
    }
  }

}
