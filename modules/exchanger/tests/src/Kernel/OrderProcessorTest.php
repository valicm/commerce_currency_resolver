<?php

namespace Drupal\Tests\commerce_currency_resolver_exchanger\Kernel;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
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

    // A rate of one would let a conversion that only relabels the currency
    // pass, so keep the two currencies genuinely apart: 1 GBP is 2 USD.
    $this->container->get('commerce_exchanger.manager')->setLatest($exchange_rates->id(), [
      'GBP' => [
        'USD' => [
          'value' => 2,
          'manual' => 0,
        ],
      ],
      'USD' => [
        'GBP' => [
          'value' => 0.5,
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
    self::assertSame(3964, (int) $first_item->getUnitPrice()->getNumber());
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
    self::assertSame(3964, (int) $first_item->getUnitPrice()->getNumber());
  }

  /**
   * An overridden unit price is converted and stays overridden.
   *
   * OrderRefresh only resolves a new price when the unit price is not
   * overridden, so a manually priced item would otherwise keep its old
   * currency and every later recalculateTotalPrice() would throw.
   *
   * @covers ::process
   */
  public function testOverriddenUnitPriceIsConverted(): void {
    $variation = $this->createVariation('500');
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'purchased_entity' => $variation,
    ]);
    $order_item->setUnitPrice(new Price('50', 'GBP'), TRUE);
    $order_item->save();

    $first_item = $this->refreshWithItems([$order_item]);

    // 50 GBP at two dollars to the pound. Had the override been dropped, the
    // refresh would have resolved the variation's own 500 USD instead.
    self::assertEquals(new Price('100', 'USD'), $first_item->getUnitPrice());
    self::assertTrue($first_item->isUnitPriceOverridden());
    self::assertEquals(new Price('100', 'USD'), $this->order->getTotalPrice());
  }

  /**
   * A resolvable unit price is left to the price resolver.
   *
   * @covers ::process
   */
  public function testResolvableUnitPriceIsLeftAlone(): void {
    $variation = $this->createVariation('500');
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'purchased_entity' => $variation,
    ]);
    // Not overridden, so the refresh resolves the variation price rather than
    // converting the 50 GBP that is on the item now.
    $order_item->setUnitPrice(new Price('50', 'GBP'));
    $order_item->save();

    $first_item = $this->refreshWithItems([$order_item]);

    self::assertEquals(new Price('500', 'USD'), $first_item->getUnitPrice());
    self::assertFalse($first_item->isUnitPriceOverridden());
  }

  /**
   * A locked order item adjustment is converted.
   *
   * Locked adjustments survive Order::clearAdjustments(), and the order level
   * loop in the processor never sees the ones sitting on an item.
   *
   * @covers ::process
   */
  public function testLockedOrderItemAdjustmentIsConverted(): void {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
    ]);
    $order_item->setUnitPrice(new Price('50', 'GBP'));
    $order_item->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Handling',
      'amount' => new Price('10', 'GBP'),
      'locked' => TRUE,
    ]));
    $order_item->save();

    $first_item = $this->refreshWithItems([$order_item]);

    $adjustments = $first_item->getAdjustments();
    self::assertCount(1, $adjustments);
    self::assertEquals(new Price('20', 'USD'), $adjustments[0]->getAmount());
    self::assertTrue($adjustments[0]->isLocked());
    // 100.00 converted unit price plus the 20.00 adjustment.
    self::assertEquals(new Price('120', 'USD'), $this->order->getTotalPrice());
  }

  /**
   * An unlocked order item adjustment is left to its own order processor.
   *
   * @covers ::process
   */
  public function testUnlockedOrderItemAdjustmentIsNotConverted(): void {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
    ]);
    $order_item->setUnitPrice(new Price('50', 'GBP'));
    $order_item->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Promotion',
      'amount' => new Price('10', 'GBP'),
      'locked' => FALSE,
    ]));
    $order_item->save();

    $first_item = $this->refreshWithItems([$order_item]);

    // The refresh clears unlocked adjustments before the processors run, so
    // there is nothing left in the old currency to convert.
    self::assertEmpty($first_item->getAdjustments());
    self::assertEquals(new Price('100', 'USD'), $this->order->getTotalPrice());
  }

  /**
   * A locked order level adjustment is converted.
   *
   * @covers ::process
   */
  public function testLockedOrderAdjustmentIsConverted(): void {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
    ]);
    $order_item->setUnitPrice(new Price('50', 'GBP'));
    $order_item->save();

    $this->order->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Gift wrapping',
      'amount' => new Price('10', 'GBP'),
      'locked' => TRUE,
    ]));

    $first_item = $this->refreshWithItems([$order_item]);

    self::assertEquals(new Price('100', 'USD'), $first_item->getUnitPrice());
    $adjustments = $this->order->getAdjustments();
    self::assertCount(1, $adjustments);
    self::assertEquals(new Price('20', 'USD'), $adjustments[0]->getAmount());
    self::assertEquals(new Price('120', 'USD'), $this->order->getTotalPrice());
  }

  /**
   * An order mixing every convertible case still saves.
   *
   * Leaving a single line behind in the old currency makes every later
   * recalculateTotalPrice() throw a CurrencyMismatchException, at which point
   * the order can no longer be saved at all.
   *
   * @covers ::process
   */
  public function testOrderWithEveryConvertibleCaseSaves(): void {
    $variation = $this->createVariation('500');

    $overridden = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'purchased_entity' => $variation,
    ]);
    $overridden->setUnitPrice(new Price('50', 'GBP'), TRUE);
    $overridden->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Handling',
      'amount' => new Price('10', 'GBP'),
      'locked' => TRUE,
    ]));
    $overridden->save();

    $without_purchasable_entity = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
    ]);
    $without_purchasable_entity->setUnitPrice(new Price('25', 'GBP'));
    $without_purchasable_entity->save();

    $this->order->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Gift wrapping',
      'amount' => new Price('5', 'GBP'),
      'locked' => TRUE,
    ]));

    $this->refreshWithItems([$overridden, $without_purchasable_entity]);

    foreach ($this->order->getItems() as $item) {
      self::assertSame('USD', $item->getUnitPrice()->getCurrencyCode());
      foreach ($item->getAdjustments() as $adjustment) {
        self::assertSame('USD', $adjustment->getAmount()->getCurrencyCode());
      }
    }
    foreach ($this->order->getAdjustments() as $adjustment) {
      self::assertSame('USD', $adjustment->getAmount()->getCurrencyCode());
    }

    // 100.00 + 20.00 handling + 50.00 + 10.00 gift wrapping.
    self::assertEquals(new Price('180', 'USD'), $this->order->getTotalPrice());

    // The order is still saveable, which is what the mismatch used to break.
    $this->order->save();
    self::assertEquals(new Price('180', 'USD'), $this->order->getTotalPrice());
  }

  /**
   * Puts the items on the order and refreshes it as its customer.
   *
   * The order is only refreshed for the user who owns it, so the customer has
   * to be assigned and switched to before the processors run.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items
   *   The order items.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   The first order item, reloaded.
   */
  protected function refreshWithItems(array $order_items): OrderItemInterface {
    $this->order->set('order_items', $order_items);
    $this->order->save();

    $this->setCurrentUser($this->user);
    $this->order->setCustomer($this->user);
    $this->order->save();

    $items = $this->order->getItems();
    self::assertInstanceOf(OrderItemInterface::class, $items[0]);

    return $items[0];
  }

  /**
   * Creates a product variation priced in USD.
   */
  protected function createVariation(string $number): ProductVariationInterface {
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'price' => new Price($number, 'USD'),
      'status' => 1,
    ]);
    $variation->save();

    $product = Product::create([
      'type' => 'default',
      'title' => 'Test product',
      'stores' => [$this->store],
      'variations' => [$variation],
    ]);
    $product->save();

    return $this->reloadEntity($variation);
  }

}
