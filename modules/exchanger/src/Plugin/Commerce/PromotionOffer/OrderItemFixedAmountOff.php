<?php

namespace Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\PromotionOffer;

use Drupal\commerce\ConditionManagerInterface;
use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerConditionTrait;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerOrderItemFixedAmountTrait;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderItemFixedAmountOff as CommerceOrderItemFixedAmountOff;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides percentage off offer for order items with multi-currency support.
 *
 * @see \Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderItemFixedAmountOff
 */
class OrderItemFixedAmountOff extends CommerceOrderItemFixedAmountOff {

  use ExchangerConditionTrait;
  use ExchangerOrderItemFixedAmountTrait;

  /**
   * The condition manager.
   *
   * @var \Drupal\commerce\ConditionManagerInterface
   */
  protected ConditionManagerInterface $conditionManager;

  protected CurrencyResolverManagerInterface $currencyResolverManager;

  protected CurrentCurrencyInterface $currentCurrency;
  protected ExchangerCalculatorInterface $priceExchanger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->conditionManager = $container->get('plugin.manager.commerce_condition');
    $instance->currencyResolverManager = $container->get('commerce_currency_resolver.manager');
    $instance->currentCurrency = $container->get('commerce_price.current_currency');
    $instance->priceExchanger = $container->get('commerce_currency_resolver_exchanger.calculator');
    return $instance;
  }

}
