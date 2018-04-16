<?php

namespace Drupal\commerce_currency_resolver\Cache\Context;

use Drupal\commerce_currency_resolver\CurrentCurrency;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines the CurrencyCacheContext service, for "per currency" caching.
 *
 * Cache context ID: 'currency_resolver'.
 */
class CurrencyCacheContext implements CacheContextInterface {

  /**
   * The current store.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrency
   */
  protected $currentCurrency;

  /**
   * Constructs a new StoreCacheContext class.
   *
   * @param \Drupal\commerce_currency_resolver\CurrentCurrency $current_currency
   *   The current currency.
   */
  public function __construct(CurrentCurrency $current_currency) {
    $this->currentCurrency = $current_currency;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Currency');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->currentCurrency->getCurrency();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
