<?php

namespace Drupal\Tests\commerce_currency_resolver_shipping\Kernel;

use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;
use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\physical\Weight;

/**
 * Tests the shipping rates resolved for an order.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod\CurrencyResolverShippingTrait
 * @group commerce_currency_resolver
 */
class CurrencyResolverShippingTest extends ShippingKernelTestBase {

  use CurrentCurrencyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_exchanger',
    'commerce_currency_resolver',
    'commerce_currency_resolver_exchanger',
    'commerce_currency_resolver_shipping',
  ];

  /**
   * The shipping method, configured with a rate of 10.00 EUR.
   */
  protected ShippingMethod $shippingMethod;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    // One euro is two dollars, so a converted rate is never mistakable for an
    // unconverted one.
    $this->container->get('commerce_exchanger.manager')->setLatest($exchange_rates->id(), [
      'EUR' => [
        'USD' => [
          'value' => 2,
          'manual' => 0,
        ],
      ],
      'USD' => [
        'EUR' => [
          'value' => 0.5,
          'manual' => 0,
        ],
      ],
    ]);

    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->set('currency_source', 'combo')
      ->save();

    // The rate is configured in a currency the store does not use, so every
    // assertion below is about the conversion rather than the configuration.
    $this->shippingMethod = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Example',
      'status' => TRUE,
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '10.00',
            'currency_code' => 'EUR',
          ],
        ],
      ],
    ]);
    $this->shippingMethod->save();

    $this->resetCurrencyContainer();
  }

  /**
   * The rate follows the order currency.
   *
   * @covers ::calculateRates
   */
  public function testRateUsesTheOrderCurrency(): void {
    $shipment = $this->createShipment($this->createOrder(new Price('30.00', 'USD')));

    $rates = $this->shippingMethod->getPlugin()->calculateRates($shipment);

    self::assertCount(1, $rates);
    self::assertEquals(new Price('20.00', 'USD'), $rates[0]->getAmount());
  }

  /**
   * The rate falls back to the resolved currency on an order with no items.
   *
   * A draft order created in the admin UI has no total price until the first
   * item is added, and a shipment can be added before that. The rate still has
   * to come out in the currency the order will use, not in whatever the
   * shipping method happens to be configured in.
   *
   * @see https://www.drupal.org/project/commerce_currency_resolver/issues/3577852
   *
   * @covers ::calculateRates
   */
  public function testRateOnOrderWithoutItems(): void {
    $order = $this->createOrder();
    self::assertNull($order->getTotalPrice());

    $rates = $this->shippingMethod->getPlugin()->calculateRates($this->createShipment($order));

    self::assertCount(1, $rates);
    self::assertEquals(new Price('20.00', 'USD'), $rates[0]->getAmount());
  }

  /**
   * A selected rate is converted into the order currency.
   *
   * @covers ::selectRate
   */
  public function testSelectedRateUsesTheOrderCurrency(): void {
    $shipment = $this->createShipment($this->createOrder(new Price('30.00', 'USD')));
    $plugin = $this->shippingMethod->getPlugin();
    // A rate still in the configured currency, as it would arrive if the
    // shipping method were not currency aware.
    $rate = new ShippingRate([
      'shipping_method_id' => $this->shippingMethod->id(),
      'service' => $plugin->calculateRates($shipment)[0]->getService(),
      'amount' => new Price('10.00', 'EUR'),
    ]);

    $plugin->selectRate($shipment, $rate);

    self::assertEquals(new Price('20.00', 'USD'), $shipment->getAmount());
  }

  /**
   * Selecting a rate on an order with no items does not fatal.
   *
   * @see https://www.drupal.org/project/commerce_currency_resolver/issues/3577852
   *
   * @covers ::selectRate
   */
  public function testSelectRateOnOrderWithoutItems(): void {
    $order = $this->createOrder();
    $shipment = $this->createShipment($order);
    $plugin = $this->shippingMethod->getPlugin();

    $plugin->selectRate($shipment, $plugin->calculateRates($shipment)[0]);

    self::assertEquals(new Price('20.00', 'USD'), $shipment->getAmount());
  }

  /**
   * Creates a draft order, with one item when a price is given.
   */
  protected function createOrder(?Price $unit_price = NULL): Order {
    $values = [
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store->id(),
    ];

    if ($unit_price) {
      $order_item = OrderItem::create([
        'type' => 'test',
        'quantity' => 1,
        'unit_price' => $unit_price,
      ]);
      $order_item->save();
      $values['order_items'] = [$order_item];
    }

    $order = Order::create($values);
    $order->save();

    return $order;
  }

  /**
   * Creates a shipment for the given order.
   */
  protected function createShipment(Order $order): ShipmentInterface {
    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => $order->id(),
      'title' => 'Shipment',
      'items' => [
        new ShipmentItem([
          'order_item_id' => 10,
          'title' => 'Item',
          'quantity' => 1,
          'weight' => new Weight('10', 'kg'),
          'declared_value' => new Price('10.00', 'USD'),
        ]),
      ],
    ]);
    $shipment->save();

    return $shipment;
  }

}
