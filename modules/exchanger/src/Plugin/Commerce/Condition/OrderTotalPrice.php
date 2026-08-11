<?php

namespace Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\Condition;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerConditionTrait;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice as CommerceOrderTotalPrice;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the total price condition for orders per currency.
 *
 * @see \Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice
 */
class OrderTotalPrice extends CommerceOrderTotalPrice implements ContainerFactoryPluginInterface {

  use ExchangerConditionTrait;

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

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    // A draft order has no total price until it holds its first item, and the
    // condition is evaluated before then: adding a shipment in the admin UI
    // runs every shipping method's conditions. getConditionAmount() takes NULL
    // and falls back to the resolved currency, and the parent returns FALSE
    // when there is no price to compare against.
    $this->configuration['amount'] = $this->getConditionAmount($order->getTotalPrice()?->getCurrencyCode());
    return parent::evaluate($order);
  }

}
