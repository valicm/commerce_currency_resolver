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

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected CurrencyResolverManagerInterface $currencyResolverManager, protected CurrentCurrencyInterface $currentCurrency, protected ExchangerCalculatorInterface $priceExchanger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_currency_resolver.manager'),
      $container->get('commerce_price.current_currency'),
      $container->get('commerce_currency_resolver_exchanger.calculator')
    );
  }

}
