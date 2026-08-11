<?php

namespace Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\Fee;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerConditionTrait;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerOrderItemFixedAmountTrait;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_fee\Plugin\Commerce\Fee\OrderItemFixedAmount as BaseOrderItemFixedAmount;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class OrderItemFixedAmount extends BaseOrderItemFixedAmount {

  use ExchangerConditionTrait;
  use ExchangerOrderItemFixedAmountTrait;

  protected CurrencyResolverManagerInterface $currencyResolverManager;

  protected CurrentCurrencyInterface $currentCurrency;

  protected ExchangerCalculatorInterface $priceExchanger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    // Build on the parent instance rather than a bare one: OrderItemFeeBase
    // and FeeBase are what set the condition manager, the rounder and the
    // merged configuration, and a handwritten list of those goes stale the
    // moment they gain a dependency.
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currencyResolverManager = $container->get('commerce_currency_resolver.manager');
    $instance->currentCurrency = $container->get('commerce_price.current_currency');
    $instance->priceExchanger = $container->get('commerce_currency_resolver_exchanger.calculator');
    return $instance;
  }

}
