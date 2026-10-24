<?php

namespace Drupal\commerce_currency_resolver_pricelist;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_pricelist\Entity\PriceListItemInterface;
use Drupal\commerce_pricelist\PriceListRepositoryInterface;

/**
 * Selects the price list item that applies in a given currency.
 *
 * Commerce Pricelist selects one item regardless of its currency. This keeps
 * only items priced in the given currency and then selects the same way
 * Commerce Pricelist does: per price list the largest quantity tier the
 * quantity reaches, then the price list with the lowest weight.
 *
 * @see \Drupal\commerce_pricelist\PriceListRepository::loadItem()
 */
class PriceListItemSelector {

  /**
   * Constructs a new PriceListItemSelector object.
   */
  public function __construct(protected PriceListRepositoryInterface $priceListRepository) {}

  /**
   * Selects the applicable price list item in a currency.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The purchasable entity.
   * @param string $quantity
   *   The quantity.
   * @param \Drupal\commerce\Context $context
   *   The context: store, customer and time decide which price lists apply.
   * @param string $currency_code
   *   The currency code the item price must be in.
   *
   * @return \Drupal\commerce_pricelist\Entity\PriceListItemInterface|null
   *   The price list item, or NULL if none applies in that currency.
   */
  public function select(PurchasableEntityInterface $entity, $quantity, Context $context, string $currency_code): ?PriceListItemInterface {
    $candidates = [];
    // Items are sorted by quantity, so the last one per price list is the
    // largest tier the quantity reaches.
    foreach ($this->priceListRepository->loadItems($entity, $context) as $price_list_item) {
      $price = $price_list_item->getPrice();
      if ($price && $price->getCurrencyCode() === $currency_code && $price_list_item->getQuantity() <= $quantity) {
        $candidates[$price_list_item->getPriceListId()] = $price_list_item;
      }
    }
    if (empty($candidates)) {
      return NULL;
    }

    // The lowest weight first, then the newest price list, the order
    // \Drupal\commerce_pricelist\PriceListRepository::loadPriceListIds() uses.
    uasort($candidates, function (PriceListItemInterface $a, PriceListItemInterface $b) {
      $a_list = $a->getPriceList();
      $b_list = $b->getPriceList();
      return [(int) $a_list->getWeight(), -(int) $a_list->id()] <=> [(int) $b_list->getWeight(), -(int) $b_list->id()];
    });

    return reset($candidates);
  }

}
