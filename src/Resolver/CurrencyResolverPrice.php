<?php

namespace Drupal\commerce_currency_resolver\Resolver;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;

/**
 * Returns a price and currency based of dedicated field.
 */
class CurrencyResolverPrice implements PriceResolverInterface {

  use CurrencyResolverPriceTrait;

  /**
   * Constructs a new CurrencyResolverPrice object.
   */
  public function __construct(protected CurrentCurrencyInterface $currentCurrency, protected ConfigFactoryInterface $configFactory) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {

    $currency_resolver_settings = $this->configFactory->get('commerce_currency_resolver.settings');
    // If we have a set source to fields or combination, try resolve
    // field first.
    if (in_array($currency_resolver_settings->get('currency_source'), [CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_FIELD, CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_COMBO], TRUE)) {
      $price = $this->getDefaultPrice($entity, $context);

      // Get current resolved currency.
      $resolved_currency = $this->currentCurrency->getCurrency()?->getCurrencyCode();

      // If we have price, and the resolved price currency is different from the
      // current currency.
      if ($resolved_currency !== $price?->getCurrencyCode()) {
        $resolved_field = $currency_resolver_settings->get('currency_field_prefix') . strtolower($resolved_currency);

        // Check if we have a field.
        if ($entity->hasField($resolved_field) && !$entity->get($resolved_field)->isEmpty()) {
          return $entity->get($resolved_field)->first()->toPrice();
        }
      }
    }

    return NULL;
  }

}
