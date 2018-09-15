<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Applies taxes to orders during the order refresh process.
 */
class CurrencyOrderProcessor implements OrderProcessorInterface {

  /**
   * The order storage.
   *
   * @var \Drupal\commerce_order\OrderStorage
   */
  protected $orderStorage;

  /**
   * Current currency.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->currentCurrency = $currency;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {

    // Get order total.
    $total = $order->getTotalPrice();

    // Get main currency.
    $currency_main = $this->currentCurrency->getCurrency();

    // This is used only to ensure that order have resolved currency.
    // In combination with check on order load we can ensure that currency is
    // same accross entire order.
    // This solution provides avoiding constant recalculation
    // on order load event on currency switch (if we don't explicitly set
    // currency for total price when we switch currency.
    // @see \Drupal\commerce_currency_resolver\EventSubscriber\OrderCurrencyRefresh
    if ($total->getCurrencyCode() !== $currency_main) {
      $refresh = $order->recalculateTotalPrice();
      $order->set('total_price', $refresh->getTotalPrice());
      $order->save();
    }

  }

}
