<?php

namespace Drupal\commerce_currency_resolver_shipping\EventSubscriber;

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
   * @var string
   */
  protected $resolvedCurrency;

  /**
   * {@inheritdoc}
   */
  public function __construct(CurrentCurrency $currency, ExchangerCalculatorInterface $price_exchanger) {
    $this->currentCurrency = $currency;
    $this->priceExchanger = $price_exchanger;
    $this->resolvedCurrency = $this->currentCurrency->getCurrency();
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
      $shipping_conversion = [];

      foreach ($rates as $key => $rate) {
        if ($rate->getAmount()->getCurrencyCode() != $this->resolvedCurrency) {
          $plugin_data = $event->getShippingMethod()->getPlugin()->getConfiguration();

          // Check if they are entered separate prices per currencies.
          if (isset($plugin_data['fields'][$this->resolvedCurrency]) && is_numeric($plugin_data['fields'][$this->resolvedCurrency]['number'])) {
            $resolved_price = new Price((string) $plugin_data['fields'][$this->resolvedCurrency]['number'], $plugin_data['fields'][$this->resolvedCurrency]['currency_code']);
            $rate->setOriginalAmount($resolved_price);
            $rate->setAmount($resolved_price);
          }

          // Otherwise just calculate automatically.
          else {
            $rate->setAmount($this->priceExchanger->priceConversion($rate->getAmount(), $this->resolvedCurrency));
            $rate->setOriginalAmount($this->priceExchanger->priceConversion($rate->getOriginalAmount(), $this->resolvedCurrency));
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
