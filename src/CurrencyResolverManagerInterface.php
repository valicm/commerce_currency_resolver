<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;

/**
 * The interface for helper.
 */
interface CurrencyResolverManagerInterface {

  /**
   * Using fields to store and resolve prices per different currencies.
   */
  public const string CURRENCY_RESOLVER_PRICE_FIELD = 'field';

  /**
   * Using everything automatically calculated.
   */
  public const string CURRENCY_RESOLVER_PRICE_AUTO = 'auto';

  /**
   * Combination of fields and auto-calculation when needed.
   */
  public const string CURRENCY_RESOLVER_PRICE_COMBO = 'combo';

  /**
   * Use as a flag to signal skipping resolver logic.
   */
  public const string CURRENCY_RESOLVER_SKIP_REFRESH = 'currency_resolver_skip_refresh';

  /**
   * Use as a flag to signal skipping resolver logic.
   */
  public const string CURRENCY_RESOLVER_FORCE_REFRESH = 'currency_resolver_force_refresh';

  /**
   * Use as a flag for other code to signal if refresh is needed.
   */
  public const string CURRENCY_ORDER_REFRESH = 'currency_order_refresh';

  /**
   * Determine if order currency needs to be refreshed.
   */
  public function shouldCurrencyRefresh(OrderInterface $order, ?CurrencyInterface $currency): bool;

  /**
   * Return formatted array of available currencies.
   *
   * @return array
   *   List of keyed currencies ['EUR' => 'Euro'].
   */
  public function getCurrencies(): array;

  /**
   * Load currency entity via currency code.
   */
  public function getCurrencyByCode(string $currency_code): ?CurrencyInterface;

  /**
   * Get cookie name.
   *
   * @return string
   *   Return cookie name.
   */
  public function getCookieName(): string;

}
