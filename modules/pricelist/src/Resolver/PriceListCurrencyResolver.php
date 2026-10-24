<?php

namespace Drupal\commerce_currency_resolver_pricelist\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver\Resolver\CurrencyResolverPriceTrait;
use Drupal\commerce_currency_resolver_pricelist\PriceListItemSelector;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\commerce_pricelist\Entity\PriceListItemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Resolves the price from the price lists, in the current currency.
 *
 * Runs before the currency resolver's price field resolver. In order:
 * - an applicable price list item in the current currency;
 * - with automatic or combo conversion, the applicable item in the price's
 *   own currency, converted: automatic conversion otherwise converts the
 *   price field and loses the price list.
 * Returns NULL otherwise, so the price fields and conversion apply as without
 * price lists. With "Price lists win over currency fields" off, it also
 * returns NULL when the product has a price field for the current currency.
 */
class PriceListCurrencyResolver implements PriceResolverInterface {

  use CurrencyResolverPriceTrait;

  /**
   * Constructs a new PriceListCurrencyResolver object.
   */
  public function __construct(protected CurrentCurrencyInterface $currentCurrency, protected ConfigFactoryInterface $configFactory, protected PriceListItemSelector $itemSelector, protected ?ExchangerCalculatorInterface $exchangerCalculator = NULL) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {
    // Price list items only have a price and a list price.
    $field_name = $context->getData('field_name', 'price');
    $currency_code = $this->currentCurrency->getCurrency()?->getCurrencyCode();
    if (!in_array($field_name, ['price', 'list_price'], TRUE) || !$currency_code) {
      return NULL;
    }

    $settings = $this->configFactory->get('commerce_currency_resolver.settings');
    $lists_win = $this->configFactory->get('commerce_currency_resolver_pricelist.settings')->get('lists_win') ?? TRUE;
    if (!$lists_win && $this->hasCurrencyField($entity, $context, $currency_code)) {
      return NULL;
    }

    if ($price_list_item = $this->itemSelector->select($entity, $quantity, $context, $currency_code)) {
      return $this->itemPrice($price_list_item, $field_name);
    }

    $default_price = $this->getDefaultPrice($entity, $context);
    $converts = in_array($settings->get('currency_source'), [
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_AUTO,
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_COMBO,
    ], TRUE);
    if ($converts && $this->exchangerCalculator && $default_price && $default_price->getCurrencyCode() !== $currency_code) {
      $price_list_item = $this->itemSelector->select($entity, $quantity, $context, $default_price->getCurrencyCode());
      if ($price_list_item && ($price = $this->itemPrice($price_list_item, $field_name))) {
        return $this->exchangerCalculator->priceConversion($price, $currency_code);
      }
    }

    return NULL;
  }

  /**
   * Gets the price or the list price of a price list item.
   */
  protected function itemPrice(PriceListItemInterface $price_list_item, string $field_name): ?Price {
    return $field_name === 'list_price' ? $price_list_item->getListPrice() : $price_list_item->getPrice();
  }

  /**
   * Checks if the currency resolver would use a price field for the currency.
   *
   * @see \Drupal\commerce_currency_resolver\Resolver\CurrencyResolverPrice::resolve()
   */
  protected function hasCurrencyField(PurchasableEntityInterface $entity, Context $context, string $currency_code): bool {
    $settings = $this->configFactory->get('commerce_currency_resolver.settings');
    if (!in_array($settings->get('currency_source'), [
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_FIELD,
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_COMBO,
    ], TRUE)) {
      return FALSE;
    }
    $default_price = $this->getDefaultPrice($entity, $context);
    if ($default_price?->getCurrencyCode() === $currency_code) {
      return FALSE;
    }
    $field = $settings->get('currency_field_prefix') . strtolower($currency_code);
    return $entity->hasField($field) && !$entity->get($field)->isEmpty();
  }

}
