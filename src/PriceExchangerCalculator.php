<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_exchanger\AbstractExchangerCalculator;

/**
 * Class PriceExchangerCalculator.
 *
 * @package Drupal\commerce_currency_resolver
 */
class PriceExchangerCalculator extends AbstractExchangerCalculator {

  /**
   * {@inheritdoc}
   */
  public function getExchangerId() {
    $resolver_exchanger_id = $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_exchange_rates');
    if (isset($this->providers[$resolver_exchanger_id]) && $this->providers[$resolver_exchanger_id]->status()) {
      return $this->providers[$resolver_exchanger_id]->getExchangerConfigName();
    }

    return NULL;
  }

}
