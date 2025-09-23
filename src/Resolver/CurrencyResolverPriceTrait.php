<?php

namespace Drupal\commerce_currency_resolver\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;

trait CurrencyResolverPriceTrait {

  /**
   * Retrieve default price.
   *
   * @see \Drupal\commerce_price\Resolver\DefaultPriceResolver
   */
  protected function getDefaultPrice(PurchasableEntityInterface $entity, Context $context): ?Price {
    $price = NULL;
    // Get field from context.
    $field_name = $context->getData('field_name', 'price');

    // @see \Drupal\commerce_price\Resolver\DefaultPriceResolver
    if ($field_name === 'price') {
      $price = $entity->getPrice();
    }
    elseif ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $price = $entity->get($field_name)->first()->toPrice();
    }

    return $price;
  }

}
