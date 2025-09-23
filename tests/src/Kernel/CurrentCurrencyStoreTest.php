<?php

namespace Drupal\Tests\commerce_currency_resolver\Kernel;

use Drupal\Tests\commerce_price\Kernel\CurrentCurrencyTest;

/**
 * Tests current currency class.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver\CurrencyResolverManager
 * @group commerce_currency_resolver
 */
class CurrentCurrencyStoreTest extends CurrentCurrencyTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_number_pattern',
    'commerce_product',
    'commerce_order',
    'commerce_currency_resolver',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installConfig(['commerce_currency_resolver']);
    $this->installSchema('commerce_number_pattern', ['commerce_number_pattern_sequence']);
  }

}
