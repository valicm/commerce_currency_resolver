<?php

namespace Drupal\commerce_currency_resolver\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\commerce_currency_resolver\CurrentCurrencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

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
   * @var \Drupal\commerce_exchanger\ExchangerCalculatorInterface
   */
  protected $priceExchanger;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new CommerceCurrencyResolver object.
   *
   * @param \Drupal\commerce_currency_resolver\CurrentCurrencyInterface $current_currency
   *   The currency manager.
   * @param \Drupal\commerce_exchanger\ExchangerCalculatorInterface $price_exchanger
   *   Price exchanger calculator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(CurrentCurrencyInterface $current_currency, ExchangerCalculatorInterface $price_exchanger, ConfigFactoryInterface $config_factory) {
    $this->currentCurrency = $current_currency;
    $this->priceExchanger = $price_exchanger;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {

    // Default price.
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

    // Get current resolved currency.
    $resolved_currency = $this->currentCurrency->getCurrency();

    // If we have price, and the resolved price currency is different than the
    // current currency.
    if ($price && $resolved_currency !== $price->getCurrencyCode()) {

      // Loading orders trough drush, or any cli task
      // will resolve price by current conditions in which cli is
      // (country, language, current store) - this will result in
      // currency exception. We need to return existing price.
      if (PHP_SAPI === 'cli') {
        return $price;
      }

      // Get how price should be calculated.
      $currency_source = $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_source');

      // Auto-calculate price by default. Fallback for all cases regardless
      // of chosen currency source mode.
      $resolved_price = $this->priceExchanger->priceConversion($price, $resolved_currency);

      // Specific cases for field and combo. Even we had auto-calculated
      // price, in combo mode we could have field with price.
      if ($currency_source === 'combo' || $currency_source === 'field') {

        // Backward compatibility for older version, and inital setup
        // that default price fields are mapped to field_price_currency_code
        // instead to price_currency_code.
        if ($field_name === 'price') {
          $field_name = 'field_price';
        }

        $resolved_field = $field_name . '_' . strtolower($resolved_currency);

        // Check if we have field.
        if ($entity->hasField($resolved_field) && !$entity->get($resolved_field)
            ->isEmpty()) {
          $resolved_price = $entity->get($resolved_field)->first()->toPrice();
        }
      }

      return $resolved_price;

    }

  }

}
