<?php

namespace Drupal\commerce_currency_resolver_exchanger;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Replace core's token service with our own.
 */
class CommerceCurrencyResolverExchangerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $container->removeDefinition('commerce_currency_resolver.order_processor');
  }

}
