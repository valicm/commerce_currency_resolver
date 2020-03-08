<?php

namespace Drupal\Tests\commerce_currency_resolver\Traits;

/**
 * Trait CurrentCurrencyTrait.
 */
trait CurrentCurrencyTrait {

  /**
   * Reset current currency container.
   */
  protected function resetCurrencyContainer() {
    $this->container = $this->container->get('kernel')->rebuildContainer();
    $this->currentCurrency = $this->container->get('commerce_currency_resolver.current_currency');
  }

}
