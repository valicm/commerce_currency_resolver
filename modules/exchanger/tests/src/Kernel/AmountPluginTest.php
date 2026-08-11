<?php

namespace Drupal\Tests\commerce_currency_resolver_exchanger\Kernel;

use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_fee\Entity\Fee;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Promotion;

/**
 * Tests the currency aware amount plugins.
 *
 * Two things these plugins share are only reachable by applying them. Each
 * overrides create() to add the currency resolver services, and the
 * dependencies their parents inject - the price splitter, the rounder, the
 * condition manager, the configuration merged with the defaults - have to
 * survive that. Each also reads a price off the order, which is NULL until
 * the order holds its first item.
 *
 * @see https://www.drupal.org/project/commerce_currency_resolver/issues/3568323
 * @see https://www.drupal.org/project/commerce_currency_resolver/issues/3577852
 *
 * @group commerce_currency_resolver
 */
class AmountPluginTest extends OrderKernelTestBase {

  use CurrentCurrencyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_exchanger',
    'commerce_currency_resolver',
    'commerce_currency_resolver_exchanger',
    'commerce_fee',
    'commerce_promotion',
  ];

  /**
   * An order of one item at 100.00 USD.
   */
  protected Order $order;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_fee');
    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);
    $this->installConfig(['commerce_currency_resolver']);
    $this->installSchema('commerce_exchanger', ['commerce_exchanger_latest_rates']);

    $this->container->get('commerce_price.currency_importer')->import('EUR');

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
    ]);
    $exchange_rates->save();

    // One euro is two dollars, so a converted amount is never mistakable for
    // an unconverted one.
    $this->container->get('commerce_exchanger.manager')->setLatest($exchange_rates->id(), [
      'EUR' => ['USD' => ['value' => 2, 'manual' => 0]],
      'USD' => ['EUR' => ['value' => 0.5, 'manual' => 0]],
    ]);

    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->set('currency_source', 'combo')
      ->save();

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('100.00', 'USD'),
    ]);
    $order_item->save();

    $this->order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store->id(),
      'order_items' => [$order_item],
    ]);
    $this->order->save();

    $this->resetCurrencyContainer();
  }

  /**
   * The order level promotion offer keeps the price splitter.
   */
  public function testOrderFixedAmountOff(): void {
    $promotion = $this->createPromotion('order_fixed_amount_off');

    $promotion->apply($this->order);

    // 10.00 EUR off, converted to 20.00 USD and split over the one item.
    $this->assertSingleAdjustment('promotion', new Price('-20.00', 'USD'));
  }

  /**
   * The order item promotion offer keeps the condition manager.
   */
  public function testOrderItemFixedAmountOff(): void {
    $promotion = $this->createPromotion('order_item_fixed_amount_off');

    $promotion->apply($this->order);

    $this->assertSingleAdjustment('promotion', new Price('-20.00', 'USD'));
  }

  /**
   * The order level fee keeps the price splitter.
   */
  public function testOrderFixedAmountFee(): void {
    $fee = $this->createFee('order_fixed_amount');

    $fee->apply($this->order);

    $this->assertSingleAdjustment('fee', new Price('20.00', 'USD'));
  }

  /**
   * The order item fee keeps the rounder.
   *
   * Its apply() calls $this->rounder->round() directly, so a missing rounder
   * is a fatal rather than a wrong number.
   */
  public function testOrderItemFixedAmountFee(): void {
    $fee = $this->createFee('order_item_fixed_amount');

    $fee->apply($this->order);

    $this->assertSingleAdjustment('fee', new Price('20.00', 'USD'));
  }

  /**
   * The order total price condition survives an order with no items.
   *
   * Adding a shipment in the admin UI evaluates every shipping method's
   * conditions, and that can happen before the order has its first item.
   */
  public function testOrderTotalPriceConditionOnEmptyOrder(): void {
    $condition = $this->container
      ->get('plugin.manager.commerce_condition')
      ->createInstance('order_total_price', [
        'amount' => [
          'number' => '10.00',
          'currency_code' => 'EUR',
        ],
      ]);

    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store->id(),
    ]);
    $order->save();
    self::assertNull($order->getTotalPrice());

    // Nothing to compare against, so the condition simply does not apply.
    self::assertFalse($condition->evaluate($order));
  }

  /**
   * The order total price condition still compares a real total.
   */
  public function testOrderTotalPriceCondition(): void {
    $condition = $this->container
      ->get('plugin.manager.commerce_condition')
      ->createInstance('order_total_price', [
        'operator' => '>',
        'amount' => [
          'number' => '10.00',
          'currency_code' => 'EUR',
        ],
      ]);

    // 10.00 EUR is 20.00 USD, and the order is 100.00 USD.
    self::assertTrue($condition->evaluate($this->order));
  }

  /**
   * The order level offer survives an order with no items.
   */
  public function testOrderFixedAmountOffOnEmptyOrder(): void {
    $promotion = $this->createPromotion('order_fixed_amount_off');

    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store->id(),
    ]);
    $order->save();

    $promotion->apply($order);

    self::assertEmpty($order->getAdjustments());
  }

  /**
   * Creates a promotion offering 10.00 EUR off.
   */
  protected function createPromotion(string $offer_id): Promotion {
    $promotion = Promotion::create([
      'name' => 'Test promotion',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => $offer_id,
        'target_plugin_configuration' => [
          'amount' => [
            'number' => '10.00',
            'currency_code' => 'EUR',
          ],
        ],
      ],
    ]);
    $promotion->save();

    return $promotion;
  }

  /**
   * Creates a fee of 10.00 EUR.
   */
  protected function createFee(string $plugin_id): Fee {
    $fee = Fee::create([
      'name' => 'Test fee',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'plugin' => [
        'target_plugin_id' => $plugin_id,
        'target_plugin_configuration' => [
          'amount' => [
            'number' => '10.00',
            'currency_code' => 'EUR',
          ],
        ],
      ],
    ]);
    $fee->save();

    return $fee;
  }

  /**
   * Asserts the order carries exactly one adjustment of the given type.
   */
  protected function assertSingleAdjustment(string $type, Price $amount): void {
    $adjustments = [];
    foreach ($this->order->getItems() as $order_item) {
      foreach ($order_item->getAdjustments([$type]) as $adjustment) {
        $adjustments[] = $adjustment;
      }
    }
    foreach ($this->order->getAdjustments([$type]) as $adjustment) {
      $adjustments[] = $adjustment;
    }

    self::assertCount(1, $adjustments);
    self::assertEquals($amount, $adjustments[0]->getAmount());
  }

}
