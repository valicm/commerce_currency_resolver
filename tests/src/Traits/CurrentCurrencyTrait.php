<?php

namespace Drupal\Tests\commerce_currency_resolver\Traits;

/**
 * Trait used by tests.
 */
trait CurrentCurrencyTrait {

  /**
   * Current currency.
   */
  protected $currentCurrency;

  protected $container;

  /**
   * Reset current currency container.
   */
  protected function resetCurrencyContainer(): void {
    $this->container = $this->container->get('kernel')->rebuildContainer();
    $this->currentCurrency = $this->container->get('commerce_price.current_currency');
  }

}
