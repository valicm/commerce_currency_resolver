<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_currency_resolver\CurrentCurrency;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Checking for mismatch in currencies on order.
 *
 * @package Drupal\commerce_currency_resolver\EventSubscriber
 */
class OrderCurrencyRefresh implements EventSubscriberInterface {

  /**
   * The order refresh.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

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
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentCurrency $currency, OrderRefreshInterface $order_refresh, RouteMatchInterface $route_match) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->currentCurrency = $currency;
    $this->orderRefresh = $order_refresh;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.commerce_order.load' => 'checkCurrency',
    ];
    return $events;
  }

  /**
   * Check for misplace in currency. Refresh order if necessary.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function checkCurrency(OrderEvent $event) {

    // Get order.
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getOrder();

    // See if order is considered as cart.
    $cart = (bool) $order->cart->get(0)->value;

    // Disable this event in admin pages.
    $route = $this->routeMatch->getRouteObject();
    $admin_path = \Drupal::service('router.admin_context')->isAdminRoute($route);

    // Resolve only prices on cart or checkout pages.
    // We don't want alter data on administration pages.
    if ($cart && !$admin_path) {

      $total = $order->getTotalPrice();
      $subtotal = $order->getSubtotalPrice();

      // Get main currency.
      $currency_main = $this->currentCurrency->getCurrency();

      // Check if we order total and subtotal is not empty.
      // Case when you add product first time to cart,
      // order total does not exist.
      // So we don't need to do anything here.
      if ($subtotal && $total) {

        // Get order total and subtotal currency.
        $currency_total = $total->getCurrencyCode();
        $currency_subtotal = $subtotal->getCurrencyCode();

        // Handle shipping module.
        if (\Drupal::service('module_handler')->moduleExists('commerce_shipping')) {

          if ($order->hasField('shipments') || !$order->get('shipments')->isEmpty()) {
            $shipments = $order->shipments->referencedEntities();

            $updateShipping = FALSE;

            /** @var \Drupal\commerce_shipping\Entity\Shipment $shipment */
            foreach ($shipments as $key => $shipment) {
              if ($shipment->getAmount()->getCurrencyCode() !== $currency_main) {
                // Recalculate rates.
                if ($shipment->getShippingMethod()) {
                  $rates = $shipment->getShippingMethod()->getPlugin()->calculateRates($shipment);
                  if (!empty($rates)) {
                    $rate = reset($rates);
                    $shipment->getShippingMethod()->getPlugin()->selectRate($shipment, $rate);
                    $shipments[$key] = $shipment;
                    $updateShipping = TRUE;
                  }
                }
              }
            }

            if ($updateShipping) {
              $order->set('shipments', $shipments);
            }
          }
        }

        // Compare order subtotal and main resolved currency.
        // Refresh order if they are different.
        // We are comparing with order subtotal, not total
        // while total_price causes loop for refresh. Deal separately with
        // order total.
        if ($currency_subtotal !== $currency_main) {
          // Refresh order.
          $this->orderRefresh->refresh($order);
        }

        // Check order total. Convert if needed for display purposes.
        // TODO: find a better way for this.
        if ($currency_total !== $currency_main) {
          $order->recalculateTotalPrice();
        }
      }
    }
  }

}
