<?php

namespace Drupal\commerce_currency_resolver_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overwrite current currency.
 */
class CommerceCurrencyResolverTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition  = $container->getDefinition('commerce_currency_resolver.current_currency');
    $definition->setClass(CurrentCurrencyTest::class);
  }

}
