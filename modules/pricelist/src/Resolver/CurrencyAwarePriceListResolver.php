<?php

namespace Drupal\commerce_currency_resolver_pricelist\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_currency_resolver_pricelist\PriceListItemSelector;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\commerce_pricelist\PriceListPriceResolver;
use Drupal\commerce_pricelist\PriceListRepositoryInterface;

/**
 * Replaces the Commerce Pricelist resolver: only items in the current currency.
 *
 * Commerce Pricelist returns the first applicable item whatever its currency.
 * Nothing converts a resolved price later (automatic conversion converts the
 * price field before this resolver runs), so an item in another currency
 * would be shown and charged as it is.
 */
class CurrencyAwarePriceListResolver extends PriceListPriceResolver {

  /**
   * Constructs a new CurrencyAwarePriceListResolver object.
   */
  public function __construct(PriceListRepositoryInterface $price_list_repository, protected CurrentCurrencyInterface $currentCurrency, protected PriceListItemSelector $itemSelector) {
    parent::__construct($price_list_repository);
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {
    $currency_code = $this->currentCurrency->getCurrency()?->getCurrencyCode();
    if (!$currency_code) {
      return parent::resolve($entity, $quantity, $context);
    }

    $price_list_item = $this->itemSelector->select($entity, $quantity, $context, $currency_code);
    if (!$price_list_item) {
      return NULL;
    }
    $field_name = $context->getData('field_name', 'price');
    if ($field_name == 'list_price') {
      return $price_list_item->getListPrice();
    }
    if ($field_name == 'price') {
      return $price_list_item->getPrice();
    }
    return NULL;
  }

}
