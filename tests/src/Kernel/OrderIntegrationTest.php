<?php

namespace Drupal\Tests\commerce_currency_resolver\Kernel;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests integration with orders.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver\EventSubscriber\CurrencyOrderRefresh
 * @group commerce_currency_resolver
 */
class OrderIntegrationTest extends OrderKernelTestBase {

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The current currency.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'commerce_exchanger',
    'commerce_currency_resolver',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Add additional currency.
    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('HRK');

    $this->installConfig(['commerce_currency_resolver']);
    $user = $this->createUser(['mail' => $this->randomString() . '@example.com']);

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
      ]
    );
    $exchange_rates->save();

    $this->config($exchange_rates->getExchangerConfigName())->setData([
      'rates' => [
        'HRK' => [
          'USD' => [
            'value' => 0.15,
            'sync' => 0,
          ],
        ],
        'USD' => [
          'HRK' => [
            'value' => 6.85,
            'sync' => 0,
          ],
        ],
      ],
    ])->save();

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

    $this->currentCurrency = $this->container->get('commerce_currency_resolver.current_currency');
  }

  /**
   * Tests the refresh of orders loading in CLI mode.
   *
   * @covers ::checkCurrency
   * @covers ::shouldCurrencyRefresh
   */
  public function testCliMode() {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => '1',
      'unit_price' => new Price('12.00', 'HRK'),
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();

    $order = Order::load(1);
    $this->assertInstanceOf(OrderInterface::class, $order);
    $this->assertEquals('cli', PHP_SAPI);
    $this->assertEquals('HRK', $this->order->getTotalPrice()->getCurrencyCode());
    $this->assertEquals('USD', $this->currentCurrency->getCurrency());
  }

}
