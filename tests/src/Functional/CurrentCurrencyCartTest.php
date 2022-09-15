<?php

namespace Drupal\Tests\commerce_currency_resolver\Functional;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\Order;
use Drupal\Tests\commerce_cart\Functional\CartBrowserTestBase;
use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;

/**
 * Tests the add to cart form with mixed currencies.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver\EventSubscriber\CurrencyOrderRefresh
 * @covers \Drupal\commerce_currency_resolver\CurrencyOrderProcessor
 * @covers \Drupal\commerce_currency_resolver\Resolver\CommerceCurrencyResolver
 * @group commerce_currency_resolver
 */
class CurrentCurrencyCartTest extends CartBrowserTestBase {

  use CurrentCurrencyTrait;

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
    'commerce_test',
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

    $this->store->setDefaultCurrencyCode('HRK');
    $this->store->save();
    $this->reloadEntity($this->store);

    $this->currentCurrency = $this->container->get('commerce_currency_resolver.current_currency');

    $variation_display = commerce_get_entity_display('commerce_product_variation', 'default', 'view');
    $variation_display->setComponent('price', [
      'label' => 'above',
      'type' => 'commerce_price_calculated',
      'settings' => [],
    ]);
    $variation_display->save();
  }

  /**
   * Test adding a product to the cart.
   *
   * @covers ::checkCurrency
   * @covers ::shouldCurrencyRefresh
   * @covers ::checkOrderOwner
   * @covers \Drupal\commerce_currency_resolver\CurrencyOrderProcessor::process
   * @covers \Drupal\commerce_currency_resolver\Resolver\CommerceCurrencyResolver::resolve
   */
  public function testProductAddToCartForm() {
    $this->assertEquals('USD', $this->variation->getPrice()->getCurrencyCode());
    $this->assertEquals('999', $this->variation->getPrice()->getNumber());
    $this->assertEquals('HRK', $this->currentCurrency->getCurrency());

    // Confirm that the initial add to cart submit works.
    $this->postAddToCart($this->variation->getProduct());
    $this->assertSession()->pageTextContains('HRK6,843.15');
    $this->cart = Order::load($this->cart->id());

    $this->drupalGet('cart');
    $this->assertEquals(999 * 6.85, $this->cart->getTotalPrice()->getNumber());
    $this->assertEquals('HRK', $this->cart->getTotalPrice()->getCurrencyCode());

    // Check product display. And check current currency.
    $this->drupalGet('product/1');
    $this->assertSession()->pageTextContains('HRK6,843.15');
    $this->assertEquals('HRK', $this->currentCurrency->getCurrency());

    // Switch currency back to USD.
    $this->store->setDefaultCurrencyCode('USD');
    $this->store->save();
    $this->reloadEntity($this->store);
    $this->resetCurrencyContainer();

    $this->assertEquals('USD', $this->currentCurrency->getCurrency());
    $this->postAddToCart($this->variation->getProduct());

    $order = Order::load($this->cart->id());
    $this->drupalGet('cart');
    $this->assertEquals(2 * 999, $order->getTotalPrice()->getNumber());
    $this->assertEquals('USD', $order->getTotalPrice()->getCurrencyCode());

  }

}
