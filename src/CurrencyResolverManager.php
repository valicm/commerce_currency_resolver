<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/**
 * Managing resolver logic around order refresh.
 */
class CurrencyResolverManager implements CurrencyResolverManagerInterface {

  /**
   * List of enabled currencies.
   */
  protected array $currencies;

  /**
   * CurrencyResolverManager constructor.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public function shouldCurrencyRefresh(OrderInterface $order, ?CurrencyInterface $currency): bool {
    $current_currency_code = $currency?->getCurrencyCode();
    // If we want to force refresh programmatically for any order.
    if ($order->getData(CurrencyResolverManagerInterface::CURRENCY_RESOLVER_FORCE_REFRESH)) {
      return TRUE;
    }

    // Only draft order should be refreshed.
    if ($order->getState()->getId() !== 'draft') {
      return FALSE;
    }

    $order_currency_code = $order->getTotalPrice()?->getCurrencyCode();

    if (!$current_currency_code || !$order_currency_code) {
      return FALSE;
    }

    if (!$order->getItems()) {
      return FALSE;
    }

    if ($order_currency_code === $current_currency_code) {
      return FALSE;
    }

    if ($order->getCustomerId() !== (int) $this->currentUser->id()) {
      return FALSE;
    }

    if (!$order->access('update')) {
      return FALSE;
    }

    // Only unlocked orders should be refreshed.
    if ($order->isLocked()) {
      return FALSE;
    }

    // If order has a specific flag set, skip refreshing currency.
    if ($order->getData(CurrencyResolverManagerInterface::CURRENCY_RESOLVER_SKIP_REFRESH)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrencies(): array {
    if (empty($this->currencies)) {
      /** @var \Drupal\commerce_price\Entity\CurrencyInterface[] $currencies */
      $currencies = $this->entityTypeManager->getStorage('commerce_currency')->loadMultiple();

      // Set defaults.
      $active_currencies = [];
      foreach ($currencies as $currency) {
        if ($currency->status()) {
          $active_currencies[$currency->getCurrencyCode()] = $currency;
        }
      }

      $this->currencies = $active_currencies;
    }

    return $this->currencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrencyByCode(string $currency_code): ?CurrencyInterface {
    $currencies = $this->getCurrencies();
    return $currencies[$currency_code] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCookieName(): string {
    return Settings::get('commerce_currency_cookie') ?? 'commerce_currency';
  }

}
