<?php

namespace Drupal\Tests\commerce_currency_resolver_exchanger\Kernel;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\user\UserInterface;

/**
 * Test the order processor with different currency altering.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver_exchanger\ExchangerOrderProcessor
 * @group commerce_currency_resolver
 */
class OrderProcessorTest extends OrderKernelTestBase {

  use CurrentCurrencyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_currency_resolver',
    'commerce_currency_resolver_exchanger',
    'commerce_cart',
    'commerce_checkout',
    'commerce_exchanger',
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_number_pattern',
    'commerce_product',
    'commerce_order',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Variation.
   */
  protected ProductVariationInterface $variation;

  /**
   * Product.
   */
  protected ProductInterface $product;

  /**
   * The order.
   */
  protected OrderInterface $order;

  /**
   * The user.
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();
    $this->installConfig(['commerce_cart']);
    $this->installConfig(['commerce_currency_resolver']);
    $this->installSchema('commerce_exchanger', ['commerce_exchanger_latest_rates']);

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

    $this->container->get('commerce_exchanger.manager')->setLatest($exchange_rates->id(), [
      'GBP' => [
        'USD' => [
          'value' => 1,
          'manual' => 0,
        ],
      ],
      'USD' => [
        'GBP' => [
          'value' => 1,
          'manual' => 0,
        ],
      ],
    ]);

    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->set('currency_source', 'combo')
      ->save();
    // Create user.
    $this->user = $this->drupalCreateUser();

    $this->resetCurrencyContainer();
  }

  /**
   * Add order item with different currency trough cart manager.
   *
   * @covers ::process
   */
  public function testCartManagerAddOrderItem(): void {
    self::assertSame('USD', $this->currentCurrency->getCurrency()->getCurrencyCode());
    self::assertSame('USD', $this->store->getDefaultCurrencyCode());
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

    // Programmatically created, user does not have update access.
    self::assertSame('GBP', $this->order->getTotalPrice()->getCurrencyCode());

    // Assign user and switch to it.
    $this->setCurrentUser($this->user);
    $this->order->setCustomer($this->user);
    $this->order->save();

    $order_items = $this->order->getItems();
    $first_item = $order_items[0];

    self::assertInstanceOf(OrderItemInterface::class, $first_item);
    self::assertSame('USD', $first_item->getUnitPrice()->getCurrencyCode());
    self::assertSame(1982, (int) $first_item->getUnitPrice()->getNumber());
  }

  /**
   * Alter order items directly on order.
   *
   * @covers ::process
   */
  public function testSetOrderItems(): void {
    self::assertSame('USD', $this->currentCurrency->getCurrency()->getCurrencyCode());
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

    // Assign user and switch to it.
    $this->setCurrentUser($this->user);
    $this->order->setCustomer($this->user);
    $this->order->save();

    $order_items = $this->order->getItems();
    $first_item = $order_items[0];

    self::assertInstanceOf(OrderItemInterface::class, $first_item);
    self::assertSame('USD', $first_item->getUnitPrice()->getCurrencyCode());
    self::assertSame(1982, (int) $first_item->getUnitPrice()->getNumber());
  }

}
