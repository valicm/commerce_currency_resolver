<?php

namespace Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerConditionTrait;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerOrderFixedAmountTrait;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderFixedAmountOff as CommerceOrderFixedAmountOff;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the fixed amount off offer for orders with multi-currency support.
 *
 * @see \Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderFixedAmountOff
 */
class OrderFixedAmountOff extends CommerceOrderFixedAmountOff {

  use ExchangerConditionTrait;
  use ExchangerOrderFixedAmountTrait;

  protected CurrencyResolverManagerInterface $currencyResolverManager;

  protected CurrentCurrencyInterface $currentCurrency;

  protected ExchangerCalculatorInterface $priceExchanger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currencyResolverManager = $container->get('commerce_currency_resolver.manager');
    $instance->currentCurrency = $container->get('commerce_price.current_currency');
    $instance->priceExchanger = $container->get('commerce_currency_resolver_exchanger.calculator');
    return $instance;
  }

}
