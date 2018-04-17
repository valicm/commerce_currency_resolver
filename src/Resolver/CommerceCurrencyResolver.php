<?php

namespace Drupal\commerce_currency_resolver\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_currency_resolver\CurrencyHelper;
use Drupal\commerce_currency_resolver\CurrentCurrencyInterface;

/**
 * Returns a price and currency depending of language or country.
 */
class CommerceCurrencyResolver implements PriceResolverInterface {

  /**
   * The current currency.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * Constructs a new CommerceCurrencyResolver object.
   *
   * @param \Drupal\commerce_currency_resolver\CurrentCurrencyInterface $current_currency
   *   The currency manager.
   */
  public function __construct(CurrentCurrencyInterface $current_currency) {
    $this->currentCurrency = $current_currency;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {
    // Get resolved currency.
    $convert_to = $this->currentCurrency->getCurrency();

    // Get default commerce field price currency and amount.
    $field_currency = $entity->getPrice()->getCurrencyCode();

    // Convert if we have mapping request. Target only currency which are
    // different then field currency.
    if ($field_currency !== $convert_to) {
      $currency_source = \Drupal::config('commerce_currency_resolver.settings')
        ->get('currency_source');

      switch ($currency_source) {
        case 'combo':
        case 'field':

          // Check if we have field.
          if ($entity->hasField('field_price_' . strtolower($convert_to))) {
            $price = $entity->get('field_price_' . strtolower($convert_to))
              ->getValue();
            $price = reset($price);

            // Return price.
            return new Price($price['number'], $price['currency_code']);
          }

          else {
            // Calculate conversion for the combo mode if field does not
            // exist.
            if ($currency_source === 'combo') {
              $new_price = CurrencyHelper::priceConversion($entity->getPrice(), $convert_to);
              return $new_price;
            }
          }
          break;

        default:
        case 'auto':
          // Calculate conversion.
          $new_price = CurrencyHelper::priceConversion($entity->getPrice(), $convert_to);
          return $new_price;

      }
    }
  }

}
