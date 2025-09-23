<?php

namespace Drupal\commerce_currency_resolver_exchanger\Resolver;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver\Resolver\CurrencyResolverPriceTrait;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;

/**
 * Returns a price and currency depending on language or country.
 */
class ExchangerResolverPrice implements PriceResolverInterface {


  use CurrencyResolverPriceTrait;

  /**
   * Constructs a new ExchangerResolverPrice object.
   */
  public function __construct(protected CurrentCurrencyInterface $currentCurrency, protected ConfigFactoryInterface $configFactory, protected ExchangerCalculatorInterface $exchangerCalculator) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {
    $currency_source = $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_source');

    if (in_array($currency_source, [
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_AUTO,
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_COMBO,
    ], TRUE)) {
      $price = $this->getDefaultPrice($entity, $context);
      // Get current resolved currency.
      $resolved_currency = $this->currentCurrency->getCurrency()?->getCurrencyCode();

      // If we have price, and the resolved price currency is different from the
      // current currency.
      if ($price && $resolved_currency !== $price->getCurrencyCode()) {
        // Auto-calculate price by default.
        return $this->exchangerCalculator->priceConversion($price, $resolved_currency);
      }
      if ($price && $currency_source === CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_COMBO) {
        return $price;
      }
    }

    return NULL;
  }

}
