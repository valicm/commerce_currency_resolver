<?php

namespace Drupal\Tests\commerce_currency_resolver_pricelist\Kernel;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\Entity\PriceListInterface;
use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;

/**
 * Tests price list prices with automatic conversion.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver_pricelist\Resolver\PriceListCurrencyResolver
 * @group commerce_currency_resolver
 */
class PriceListExchangerTest extends PriceListCurrencyKernelTestBase {

  use CurrentCurrencyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_exchanger',
    'commerce_currency_resolver_exchanger',
  ];

  /**
   * A list with a dollar price only.
   */
  protected PriceListInterface $priceList;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('commerce_exchanger', ['commerce_exchanger_latest_rates']);
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
    $this->container->get('commerce_exchanger.manager')->setLatest($exchange_rates->id(), [
      'USD' => ['EUR' => ['value' => 0.5, 'manual' => 0]],
      'EUR' => ['USD' => ['value' => 2, 'manual' => 0]],
    ]);
    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->set('currency_source', 'combo')
      ->save();
    // The calculator reads the exchange rates setting when it is created.
    $this->resetCurrencyContainer();

    $this->priceList = $this->createPriceList('Dollars');
    $this->addItem($this->priceList, new Price('80', 'USD'));
  }

  /**
   * Tests that the list price is converted, rather than the variation price.
   *
   * @covers ::resolve
   */
  public function testConvertedList(): void {
    $this->setCurrency('EUR');
    $this->assertEquals(new Price('40', 'EUR'), $this->resolve(), 'The list wins over the euro field, converted.');

    $this->config('commerce_currency_resolver_pricelist.settings')->set('lists_win', FALSE)->save();
    $this->assertEquals(new Price('90', 'EUR'), $this->resolve(), 'The euro field wins when set to.');

    $this->config('commerce_currency_resolver.settings')->set('currency_source', 'auto')->save();
    $this->assertEquals(new Price('40', 'EUR'), $this->resolve(), 'Automatic conversion ignores the field.');

    $this->setCurrency('USD');
    $this->assertEquals(new Price('80', 'USD'), $this->resolve());
  }

  /**
   * Tests that an item in the current currency is not converted.
   *
   * @covers ::resolve
   */
  public function testCurrentCurrencyItem(): void {
    $this->addItem($this->priceList, new Price('35', 'EUR'));
    $this->setCurrency('EUR');
    $this->assertEquals(new Price('35', 'EUR'), $this->resolve());
  }

  /**
   * Tests that without price lists, conversion works as before.
   *
   * @covers ::resolve
   */
  public function testNoList(): void {
    $this->priceList->setEnabled(FALSE)->save();
    $this->config('commerce_currency_resolver.settings')->set('currency_source', 'auto')->save();
    $this->setCurrency('EUR');
    $this->assertEquals(new Price('50', 'EUR'), $this->resolve(), 'The variation price, converted.');
  }

  /**
   * Tests that an order keeps the list price, without converting it again.
   */
  public function testOrderItem(): void {
    $this->addItem($this->priceList, new Price('35', 'EUR'));
    $this->setCurrency('EUR');
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $this->variation,
      'quantity' => 1,
    ]);
    $order_item->save();
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'uid' => $this->customer->id(),
      'state' => 'draft',
      'order_items' => [$order_item],
    ]);
    $order->save();
    $this->assertEquals(new Price('35', 'EUR'), $order->getItems()[0]->getUnitPrice());
    $this->assertEquals(new Price('35', 'EUR'), $order->getTotalPrice());
  }

}
