<?php

namespace Drupal\Tests\commerce_currency_resolver_shipping\FunctionalJavascript;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;
use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;

/**
 * Tests integration with the shipping module.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver_shipping\EventSubscriber\CommerceShippingCurrency
 * @covers \Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod\FlatRateCurrency
 * @covers \Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod\FlatRatePerItemCurrency
 * @covers \Drupal\commerce_currency_resolver_shipping\ShippingCurrencyOrderProcessor
 * @group commerce_currency_resolver
 */
class ShippingIntegrationTest extends CommerceWebDriverTestBase {

  use CurrentCurrencyTrait;

  /**
   * First sample product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $firstProduct;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_checkout',
    'commerce_payment',
    'commerce_payment_example',
    'commerce_shipping',
    'commerce_exchanger',
    'commerce_currency_resolver',
    'commerce_currency_resolver_shipping',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'access checkout',
    ], parent::getAdministratorPermissions());
  }

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

    $this->store->setDefaultCurrencyCode('USD');

    /** @var \Drupal\commerce_payment\Entity\PaymentGateway $gateway */
    $gateway = PaymentGateway::create([
      'id' => 'example_onsite',
      'label' => 'Example',
      'plugin' => 'example_onsite',
    ]);
    $gateway->getPlugin()->setConfiguration([
      'api_key' => '2342fewfsfs',
      'payment_method_types' => ['credit_card'],
    ]);
    $gateway->save();

    $product_variation_type = ProductVariationType::load('default');
    $product_variation_type->setTraits(['purchasable_entity_shippable']);
    $product_variation_type->save();

    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_checkout', 'checkout_flow', 'shipping');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();

    // Create the order field.
    $field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
    $this->container->get('commerce.configurable_field_manager')
      ->createField($field_definition);

    // Install the variation trait.
    $trait_manager = $this->container->get('plugin.manager.commerce_entity_trait');
    $trait = $trait_manager->createInstance('purchasable_entity_shippable');
    $trait_manager->installTrait($trait, 'commerce_product_variation', 'default');

    // Create product.
    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '10',
        'currency_code' => 'HRK',
      ],
      'weight' => [
        'number' => '20',
        'unit' => 'g',
      ],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $this->firstProduct = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'Conference hat',
      'variations' => [$variation],
      'stores' => [$this->store],
    ]);

    /** @var \Drupal\commerce_shipping\Entity\PackageType $package_type */
    $package_type = $this->createEntity('commerce_package_type', [
      'id' => 'package_type_a',
      'label' => 'Package Type A',
      'dimensions' => [
        'length' => 20,
        'width' => 20,
        'height' => 20,
        'unit' => 'mm',

      ],
      'weight' => [
        'number' => 20,
        'unit' => 'g',
      ],
    ]);
    $this->container->get('plugin.manager.commerce_package_type')
      ->clearCachedDefinitions();

    // Create a flat rate per item shipping method to make testing adjustments
    // in items easier.
    $this->createEntity('commerce_shipping_method', [
      'name' => 'Flat Rate Per Item',
      'stores' => [$this->store->id()],
      'plugin' => [
        'target_plugin_id' => 'flat_rate_per_item',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat Rate Per Item',
          'rate_amount' => [
            'number' => '10.00',
            'currency_code' => 'HRK',
          ],
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'shipment_weight',
          'target_plugin_configuration' => [
            'operator' => '<',
            'weight' => [
              'number' => '120',
              'unit' => 'g',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Test for recalculating shipping trough cart/checkout steps.
   *
   * @covers ::shippingCurrency
   * @covers \Drupal\commerce_currency_resolver_shipping\ShippingCurrencyOrderProcessor::process
   */
  public function testRecalculateShippingPricing() {
    // Create a flat rate.
    $this->createEntity('commerce_shipping_method', [
      'name' => 'Flat Rate',
      'stores' => [$this->store->id()],
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Free Shipping',
          'rate_amount' => [
            'number' => '0.00',
            'currency_code' => 'HRK',
          ],
          'fields' => [
            'USD' => [
              'number' => '1.00',
              'currency_code' => 'USD',
            ],
            'HRK' => [
              'number' => '0.00',
              'currency_code' => 'HRK',
            ],
          ],
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'shipment_weight',
          'target_plugin_configuration' => [
            'operator' => '>',
            'weight' => [
              'number' => '120',
              'unit' => 'g',
            ],
          ],
        ],
      ],
    ]);

    // Add product to order and calculate shipping.
    $this->drupalGet($this->firstProduct->toUrl()->toString());
    // We don't have calculated price formatter active.
    $this->assertSession()->pageTextContains('HRK10.00');
    $this->assertSession()->pageTextContains('Conference hat');
    $this->submitForm([], 'Add to cart');
    $this->drupalGet('checkout/1');
    $address = [
      'given_name' => 'John',
      'family_name' => 'Smith',
      'address_line1' => '1098 Alta Ave',
      'locality' => 'Mountain View',
      'administrative_area' => 'CA',
      'postal_code' => '94043',
    ];
    $address_prefix = 'shipping_information[shipping_profile][address][0][address]';
    $this->getSession()
      ->getPage()
      ->fillField($address_prefix . '[country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach ($address as $property => $value) {
      $this->getSession()
        ->getPage()
        ->fillField($address_prefix . '[' . $property . ']', $value);
    }
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->submitForm([
      'payment_information[add_payment_method][payment_details][number]' => '4111111111111111',
      'payment_information[add_payment_method][payment_details][expiration][month]' => '02',
      'payment_information[add_payment_method][payment_details][expiration][year]' => '2023',
      'payment_information[add_payment_method][payment_details][security_code]' => '123',
    ], 'Continue to review');

    $this->assertSession()->pageTextContains('Shipping $1.50');

    // Test whether the shipping amount gets updated.
    $this->drupalGet('/cart');
    $this->getSession()->getPage()->fillField('edit_quantity[0]', 10);
    $this->getSession()->getPage()->findButton('Update cart')->click();
    $this->assertSession()->pageTextContains('Shipping $1.00');

    $this->drupalGet('checkout/1');
    $this->assertSession()->pageTextContains('Shipping $1.00');

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Shipping method');
    $this->assertSession()->pageTextContains('Shipping $1.00');

    // Switch currency default to HRK.
    $this->store->setDefaultCurrencyCode('HRK');
    $this->store->save();
    $this->reloadEntity($this->store);

    $this->getSession()->getPage()->findButton('Continue to review')->click();
    $this->assertSession()->pageTextContains('Shipping HRK0.00');

    $this->drupalGet('cart');
    $this->assertSession()->pageTextContains('Shipping HRK0.00');
    $this->getSession()->getPage()->fillField('edit_quantity[0]', 3);
    $this->getSession()->getPage()->findButton('Update cart')->click();
    $this->assertSession()->pageTextContains('Shipping HRK30.00');

    $this->drupalGet('checkout/1');
    $this->assertSession()->pageTextContains('Shipping HRK30.00');

  }

}
