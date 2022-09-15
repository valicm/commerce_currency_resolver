<?php

namespace Drupal\Tests\commerce_currency_resolver\FunctionalJavascript;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\Tests\commerce_order\FunctionalJavascript\OrderAdminTest;

/**
 * Tests the order admin UI.
 *
 * @group commerce_currency_resolver
 */
class CurrentCurrencyOrderAdminTest extends OrderAdminTest {

  /**
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
    'commerce_currency_resolver_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Add additional currency.
    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('VUV');

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
        'VUV' => [
          'USD' => [
            'value' => 0.00878642,
            'sync' => 0,
          ],
        ],
        'USD' => [
          'VUV' => [
            'value' => 113.812,
            'sync' => 0,
          ],
        ],
      ],
    ])->save();

    // Use cookie mapping for this tests, and set default value
    // to HRK for currency.
    // Don't use store, while in core commerce there are some
    // price override trough UI done based on one currency.
    // Changing currency for store most certain will lead to
    // order tried to be saved with multiple different currencies.
    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->set('currency_default', 'VUV')
      ->set('currency_mapping', 'cookie')
      ->save();

    $this->store->setDefaultCurrencyCode('USD');
    $this->store->save();
    $this->reloadEntity($this->store);

    $this->currentCurrency = $this->container->get('commerce_currency_resolver.current_currency');
  }

  /**
   * Tests creating an order.
   */
  public function testCreateOrder() {
    // Create an order through the add form.
    $this->drupalGet('/admin/commerce/orders');

    // Verify that we have different currencies on store and one
    // with current currency check.
    $this->assertEquals($this->store->getDefaultCurrencyCode(), 'USD');
    $this->assertEquals($this->currentCurrency->getCurrency(), 'VUV');
    $this->assertEquals(\Drupal::service('commerce_currency_resolver.current_currency')->getCurrency(), 'VUV');
    parent::testCreateOrder();
  }

}
