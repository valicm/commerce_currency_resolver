<?php

namespace Drupal\Tests\commerce_currency_resolver_exchanger\Kernel;

use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;

/**
 * Tests integration with orders.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver\EventSubscriber\CurrencyOrderSubscriber
 * @covers \Drupal\commerce_currency_resolver\CurrencyResolverManager
 * @group commerce_currency_resolver
 */
class OrderIntegrationTest extends OrderKernelTestBase {

  use CurrentCurrencyTrait;

  /**
   * A sample order.
   */
  protected OrderInterface $order;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_exchanger',
    'commerce_currency_resolver',
    'commerce_currency_resolver_exchanger',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Add additional currency.
    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('EUR');

    $this->installConfig(['commerce_currency_resolver']);
    $this->installSchema('commerce_exchanger', ['commerce_exchanger_latest_rates']);
    $user = $this->createUser([], NULL, FALSE, ['mail' => 'valentino@example.com']);

    // Create new exchange rates.
    $exchange_rates = ExchangeRates::create([
      'id' => 'testing',
      'label' => 'Manual',
      'plugin' => 'manual',
      'status' => TRUE,
      'configuration' => [
        'cron' => FALSE,
        'use_cross_sync' => FALSE,
        'demo_amount' => 100,
        'base_currency' => 'USD',
        'mode' => 'live',
      ],
    ],
    );
    $exchange_rates->save();

    $this->container->get('commerce_exchanger.manager')->setLatest($exchange_rates->id(), [
      'EUR' => [
        'USD' => [
          'value' => 0.15,
          'manual' => 0,
        ],
      ],
      'USD' => [
        'EUR' => [
          'value' => 6.85,
          'manual' => 0,
        ],
      ],
    ]);

    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->save();

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
    ]);
    $order->save();
    $this->order = $this->reloadEntity($order);

    $this->resetCurrencyContainer();
  }

  /**
   * Tests the refresh of orders loading in CLI mode.
   *
   * @covers \Drupal\commerce_currency_resolver\CurrencyResolverManager::shouldCurrencyRefresh
   */
  public function testCliMode(): void {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => '1',
      'unit_price' => new Price('12.00', 'EUR'),
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();

    $order = Order::load(1);
    $this->assertInstanceOf(OrderInterface::class, $order);
    $this->assertEquals('EUR', $this->order->getTotalPrice()->getCurrencyCode());
    $this->assertEquals('USD', $this->currentCurrency->getCurrency()->getCurrencyCode());
  }

}
