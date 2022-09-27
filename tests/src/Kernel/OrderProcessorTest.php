<?php

namespace Drupal\Tests\commerce_currency_resolver\Kernel;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Test the order processor with different currency altering.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver\CurrencyOrderProcessor
 * @group commerce_currency_resolver
 */
class OrderProcessorTest extends OrderKernelTestBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_currency_resolver',
    'commerce_cart',
    'commerce_checkout',
    'commerce_exchanger',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $variation;

  /**
   * Product.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $product;

  /**
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();
    $this->installConfig(['commerce_cart']);
    $this->installConfig(['commerce_currency_resolver']);

    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('GBP');

    $this->order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
    ]);
    $this->order->save();

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
        'GBP' => [
          'USD' => [
            'value' => 1,
            'sync' => 0,
          ],
        ],
        'USD' => [
          'GBP' => [
            'value' => 1,
            'sync' => 0,
          ],
        ],
      ],
    ])->save();

    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->save();
    // Make sure our new exchange rate is in the calculator.
    $this->container = $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * Add order item with different currency trough cart manager.
   */
  public function testCartManagerAddOrderItem() {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
    ]);

    $order_item->setUnitPrice(Price::fromArray([
      'number' => '1982',
      'currency_code' => 'GBP',
    ]));
    $order_item->save();
    $this->container->get('commerce_cart.cart_manager')->addOrderItem($this->order, $order_item);

    self::assertSame('USD', $this->order->getTotalPrice()->getCurrencyCode());

    $order_items = $this->order->getItems();
    $first_item = $order_items[0];

    self::assertInstanceOf(OrderItemInterface::class, $first_item);
    self::assertSame('USD', $first_item->getUnitPrice()->getCurrencyCode());
    self::assertSame(1982, (int) $first_item->getUnitPrice()->getNumber());
  }

  /**
   * Alter order items directly on order.
   */
  public function testSetOrderItems() {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
    ]);
    $order_item->setUnitPrice(Price::fromArray([
      'number' => '1982',
      'currency_code' => 'GBP',
    ]));
    $order_item->save();

    $this->order->set('order_items', [$order_item]);
    $this->order->save();

    $order_items = $this->order->getItems();
    $first_item = $order_items[0];

    self::assertInstanceOf(OrderItemInterface::class, $first_item);
    self::assertSame('USD', $first_item->getUnitPrice()->getCurrencyCode());
    self::assertSame(1982, (int) $first_item->getUnitPrice()->getNumber());
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_id = 'commerce_currency_resolver.order_processor';
    $def = $container->getDefinition($service_id);
    $def->setClass(TestProcessorOverridden::class);
    $container->setDefinition($service_id, $def);
  }

}
