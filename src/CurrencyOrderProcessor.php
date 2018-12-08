<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Applies taxes to orders during the order refresh process.
 */
class CurrencyOrderProcessor implements OrderProcessorInterface {

  use CommerceCurrencyResolversRefreshTrait;

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency, AccountInterface $account, RouteMatchInterface $route_match) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->routeMatch = $route_match;
    $this->account = $account;
    $this->currentCurrency = $currency;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {

    // Get order total.
    if ($total = $order->getTotalPrice()) {

      // Get main currency.
      $resolved_currency = $this->currentCurrency->getCurrency();

      // Triggered on event order load to ensure that new currency and prices
      // are properly saved.
      // @see \Drupal\commerce_currency_resolver\EventSubscriber\OrderCurrencyRefresh
      if ($total->getCurrencyCode() !== $resolved_currency && $this->shouldCurrencyRefresh($order)) {

        // Handle shipping module.
        if (\Drupal::service('module_handler')->moduleExists('commerce_shipping')) {
          if ($order->hasField('shipments') || !$order->get('shipments')->isEmpty()) {

            // Get order shipments.
            $shipments = $order->shipments->referencedEntities();

            $update_shipments = $this->processShipments($shipments, $resolved_currency);

            if ($update_shipments) {
              $order->set('shipments', $shipments);
            }
          }
        }

        // Get new total price.
        $order = $order->recalculateTotalPrice();

        // Refresh order on load. Shipping fix. Probably all other potential
        // unlocked adjustments which are not set correctly.
        $order->setRefreshState(Order::REFRESH_ON_LOAD);

        // Save order.
        $order->save();
      }
    }

  }

  /**
   * Handle shipments on currency change.
   *
   * @param \Drupal\commerce_shipping\Entity\Shipment[] $shipments
   *   List of shipments attached to the order.
   * @param string $resolved_currency
   *   Currency code.
   *
   * @return bool|\Drupal\commerce_shipping\Entity\Shipment[]
   *   FALSE if is auto-calculated, and shipments if they need to be updated.
   */
  protected function processShipments($shipments, $resolved_currency) {

    $updateShipping = FALSE;

    foreach ($shipments as $key => $shipment) {
      if ($amount = $shipment->getAmount()) {
        if ($amount->getCurrencyCode() !== $resolved_currency) {
          // Recalculate rates.
          if ($shipment->getShippingMethod()) {

            // Get rates.
            $rates = $shipment->getShippingMethod()->getPlugin()->calculateRates($shipment);

            // If we have found match, update with new rate.
            if (!empty($rates)) {
              $rate = reset($rates);
              $shipment->getShippingMethod()->getPlugin()->selectRate($shipment, $rate);
              $shipments[$key] = $shipment;
              $updateShipping = $shipments;
            }

            // We haven't found anything, automatically convert price.
            else {
              $shipment->setAmount(CurrencyHelper::priceConversion($shipment->getAmount(), $resolved_currency));
            }
          }
        }
      }
    }

    return $updateShipping;
  }

}
