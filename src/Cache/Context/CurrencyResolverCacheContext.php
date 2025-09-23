<?php

namespace Drupal\commerce_currency_resolver\Cache\Context;

use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the CurrencyResolverCacheContext service, for "per currency" caching.
 *
 * Cache context ID: 'currency_resolver'.
 */
class CurrencyResolverCacheContext implements CacheContextInterface {

  /**
   * Constructs a new CurrencyResolverCacheContext class.
   */
  public function __construct(protected CurrentCurrencyInterface $currentCurrency) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string|TranslatableMarkup {
    return t('Currency');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): ?string {
    return $this->currentCurrency->getCurrency()?->getCurrencyCode();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return new CacheableMetadata();
  }

}
