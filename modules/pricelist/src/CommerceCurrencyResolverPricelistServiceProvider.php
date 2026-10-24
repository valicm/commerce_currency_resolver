<?php

namespace Drupal\commerce_currency_resolver_pricelist;

use Drupal\commerce_currency_resolver_pricelist\Resolver\CurrencyAwarePriceListResolver;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Makes the Commerce Pricelist price resolver currency aware.
 *
 * The class is swapped rather than decorated, so the service keeps its
 * price resolver tag and priority.
 */
class CommerceCurrencyResolverPricelistServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('commerce_pricelist.price_resolver')) {
      $container->getDefinition('commerce_pricelist.price_resolver')
        ->setClass(CurrencyAwarePriceListResolver::class)
        ->addArgument(new Reference('commerce_price.current_currency'))
        ->addArgument(new Reference('commerce_currency_resolver_pricelist.item_selector'));
    }
  }

}
