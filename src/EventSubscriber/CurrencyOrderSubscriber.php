<?php

namespace Drupal\commerce_currency_resolver\EventSubscriber;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checking for mismatch in currencies on order.
 */
class CurrencyOrderSubscriber implements EventSubscriberInterface {

  use LoggerChannelTrait;

  protected array $orderRefreshEvents = [];

  public function __construct(protected CurrencyResolverManagerInterface $currencyResolverManager, protected OrderRefreshInterface $orderRefresh, protected CurrentCurrencyInterface $currentCurrency) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.commerce_order.load' => 'checkCurrency',
    ];
  }

  /**
   * Check for misplace in currency. Refresh order if necessary.
   */
  public function checkCurrency(OrderEvent $event): void {
    $order = $event->getOrder();
    // We already have fired this event. Avoid infinite loop.
    if (isset($this->orderRefreshEvents[$order->id()])) {
      return;
    }

    $is_refresh_needed = $this->currencyResolverManager->shouldCurrencyRefresh($order, $this->currentCurrency->getCurrency());
    $this->orderRefreshEvents[$order->id()] = $is_refresh_needed;

    if ($is_refresh_needed) {
      // Check if we can refresh order.
      $this->orderRefresh->refresh($order);
      // Remove the flag once it's refreshed.
      $order->unsetData(CurrencyResolverManagerInterface::CURRENCY_ORDER_REFRESH);
      try {
        $order->save();
      }
      catch (\Exception $exception) {
        $this->getLogger('commerce_currency_resolver')->error($exception->getMessage());
      }
    }
  }

}
