<?php

namespace Drupal\Tests\commerce_currency_resolver_exchanger\FunctionalJavascript;

use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\Tests\commerce_order\FunctionalJavascript\OrderAdminTest;
use Drupal\commerce_exchanger\Entity\ExchangeRates;

/**
 * Tests the order admin UI.
 *
 * @group commerce_currency_resolver
 */
class CurrentCurrencyOrderAdminTest extends OrderAdminTest {

  /**
   * The current currency.
   */
  protected CurrentCurrencyInterface $currentCurrency;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_exchanger',
    'commerce_currency_resolver',
    'commerce_test',
    'commerce_currency_resolver_exchanger',
    'commerce_currency_resolver_cookie',
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
    ],
    );
    $exchange_rates->save();

    $this->container->get('commerce_exchanger.manager')->setLatest($exchange_rates->id(), [
      'VUV' => [
        'USD' => [
          'value' => 0.00878642,
          'manual' => 0,
        ],
      ],
      'USD' => [
        'VUV' => [
          'value' => 113.812,
          'manual' => 0,
        ],
      ],
    ]);

    $this->config('commerce_currency_resolver.settings')
      ->set('currency_exchange_rates', 'testing')
      ->save();

    $this->store->setDefaultCurrencyCode('USD');
    $this->store->save();
    $this->reloadEntity($this->store);

    $this->currentCurrency = $this->container->get('commerce_price.current_currency');
  }

}
