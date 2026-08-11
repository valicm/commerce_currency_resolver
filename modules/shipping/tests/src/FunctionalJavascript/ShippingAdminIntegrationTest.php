<?php

namespace Drupal\Tests\commerce_currency_resolver_shipping\FunctionalJavascript;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\PackageTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;
use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_shipping\Entity\ShipmentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;
use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\commerce_shipping\Entity\Shipment;

/**
 * Tests the shipment admin UI.
 *
 * @group commerce_currency_resolver
 */
class ShippingAdminIntegrationTest extends CommerceWebDriverTestBase {

  use CurrentCurrencyTrait;

  use AssertMailTrait;
  use StringTranslationTrait;

  /**
   * The default profile's address.
   *
   * @var array
   */
  protected array $defaultAddress = [
    'country_code' => 'US',
    'administrative_area' => 'SC',
    'locality' => 'Greenville',
    'postal_code' => '29616',
    'address_line1' => '9 Drupal Ave',
    'given_name' => 'Bryan',
    'family_name' => 'Centarro',
  ];

  /**
   * The default profile.
   */
  protected ProfileInterface $defaultProfile;

  /**
   * A sample order.
   */
  protected OrderInterface $order;

  /**
   * The base admin shipment uri.
   */
  protected string $shipmentUri;

  /**
   * A test package type.
   */
  protected PackageTypeInterface $packageType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_shipping_test',
    'telephone',
    'commerce_exchanger',
    'commerce_currency_resolver',
    'commerce_test',
    'commerce_product',
    'commerce_currency_resolver_shipping',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'administer commerce_shipment',
      'administer commerce_shipment_type',
      'access commerce_order overview',
      'administer commerce_payment_gateway',
      'view commerce_product',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    $product_variation_type = ProductVariationType::load('default');
    $product_variation_type->setTraits(['purchasable_entity_shippable']);
    $product_variation_type->setGenerateTitle(FALSE);
    $product_variation_type->save();

    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();

    // Create the order field.
    $field_definition = Shipment::buildShipmentsFieldDefinition($order_type->id());
    \Drupal::service('commerce.configurable_field_manager')->createField($field_definition);

    // Install the variation trait.
    $trait_manager = \Drupal::service('plugin.manager.commerce_entity_trait');
    $trait = $trait_manager->createInstance('purchasable_entity_shippable');
    $trait_manager->installTrait($trait, 'commerce_product_variation', 'default');

    $variation = $this->createEntity('commerce_product_variation', [
      'title' => $this->randomMachineName(),
      'type' => 'default',
      'sku' => 'sku-' . $this->randomMachineName(),
      'price' => [
        'number' => '7.99',
        'currency_code' => 'USD',
      ],
    ]);
    $this->createEntity('commerce_product', [
      'title' => $this->randomMachineName(),
      'type' => 'default',
      'variations' => [$variation],
      'stores' => [$this->store],
    ]);
    $order_item = $this->createEntity('commerce_order_item', [
      'title' => $this->randomMachineName(),
      'type' => 'default',
      'quantity' => 1,
      'unit_price' => new Price('10', 'USD'),
      'purchased_entity' => $variation,
    ]);
    $order_item->save();
    $this->order = $this->createEntity('commerce_order', [
      'uid' => $this->loggedInUser->id(),
      'order_number' => '6',
      'type' => 'default',
      'state' => 'completed',
      'order_items' => [$order_item],
      'store_id' => $this->store,
      'mail' => $this->loggedInUser->getEmail(),
    ]);
    $this->shipmentUri = Url::fromRoute('entity.commerce_shipment.collection', [
      'commerce_order' => $this->order->id(),
    ])->toString();

    $this->packageType = $this->createEntity('commerce_package_type', [
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
    \Drupal::service('plugin.manager.commerce_package_type')->clearCachedDefinitions();

    $this->createEntity('commerce_shipping_method', [
      'name' => 'Overnight shipping',
      'stores' => [$this->store->id()],
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'default_package_type' => 'commerce_package_type:' . $this->packageType->uuid(),
          'rate_label' => 'Overnight shipping',
          'rate_description' => 'At your door tomorrow morning',
          'rate_amount' => [
            'number' => '19.99',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $this->createEntity('commerce_shipping_method', [
      'name' => 'Standard shipping',
      'stores' => [$this->store->id()],
      // Ensure that Standard shipping shows before overnight shipping.
      'weight' => -10,
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '9.99',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);

    // Create a different shipping profile type, which also has a Phone field.
    $bundle_entity_duplicator = $this->container->get('entity.bundle_entity_duplicator');
    $customer_profile_type = ProfileType::load('customer');
    $shipping_profile_type = $bundle_entity_duplicator->duplicate($customer_profile_type, [
      'id' => 'customer_shipping',
      'label' => 'Customer (Shipping)',
    ]);
    // Add a telephone field to the new profile type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_phone',
      'entity_type' => 'profile',
      'type' => 'telephone',
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $shipping_profile_type->id(),
      'label' => 'Phone',
    ]);
    $field->save();
    $form_display = commerce_get_entity_display('profile', 'customer_shipping', 'form');
    $form_display->setComponent('field_phone', [
      'type' => 'telephone_default',
    ]);
    $form_display->save();
    $view_display = commerce_get_entity_display('profile', 'customer_shipping', 'view');
    $view_display->setComponent('field_phone', [
      'type' => 'basic_string',
    ]);
    $view_display->save();

    $shipment_type = ShipmentType::load('default');
    $shipment_type->setProfileTypeId('customer_shipping');
    $shipment_type->save();

    $this->defaultProfile = Profile::create([
      'type' => 'customer_shipping',
      'uid' => $this->adminUser,
      'address' => $this->defaultAddress,
      'field_phone' => '202-555-0108',
    ]);
    $this->defaultProfile->save();

    $this->createEntity('commerce_payment_gateway', [
      'id' => 'example',
      'label' => 'Example',
      'plugin' => 'manual',
    ]);

    // Add additional currency.
    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('VUV');

    $this->store->setDefaultCurrencyCode('VUV');
    $this->store->save();

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

    $this->resetCurrencyContainer();
  }

  /**
   * Tests that Shipments tab and operation visibility.
   */
  public function testShipmentTabAndOperation(): void {
    $this->drupalGet($this->order->toUrl());
    // Verify that we have different currencies on order and
    // currently resolved one.
    $this->assertEquals('USD', $this->order->getTotalPrice()->getCurrencyCode());
    $this->assertEquals('VUV', $this->currentCurrency->getCurrency()->getCurrencyCode());
  }

}
