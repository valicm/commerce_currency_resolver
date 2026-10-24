<?php

namespace Drupal\Tests\commerce_currency_resolver_pricelist\Kernel;

use Drupal\commerce\Context;
use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\Entity\PriceList;
use Drupal\commerce_pricelist\Entity\PriceListInterface;
use Drupal\commerce_pricelist\Entity\PriceListItem;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base for price list currency tests: a $100 variation with a €90 field.
 */
abstract class PriceListCurrencyKernelTestBase extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_pricelist',
    'commerce_currency_resolver',
    'commerce_currency_resolver_pricelist',
  ];

  /**
   * The variation.
   */
  protected ProductVariationInterface $variation;

  /**
   * The customer.
   */
  protected UserInterface $customer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('commerce_pricelist');
    $this->installEntitySchema('commerce_pricelist_item');
    $this->installConfig(['commerce_currency_resolver', 'commerce_currency_resolver_pricelist']);
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('EUR');
    $currency_importer->import('GBP');

    FieldStorageConfig::create([
      'field_name' => 'field_price_eur',
      'entity_type' => 'commerce_product_variation',
      'type' => 'commerce_price',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_price_eur',
      'entity_type' => 'commerce_product_variation',
      'bundle' => 'default',
      'label' => 'Price EUR',
    ])->save();

    $this->variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test',
      'price' => new Price('100', 'USD'),
      'field_price_eur' => new Price('90', 'EUR'),
    ]);
    $this->variation->save();
    $this->customer = $this->createUser();
  }

  /**
   * Creates a price list for the store.
   */
  protected function createPriceList(string $name, int $weight = 0, array $values = []): PriceListInterface {
    $price_list = PriceList::create($values + [
      'type' => 'commerce_product_variation',
      'name' => $name,
      'stores' => [$this->store->id()],
      'weight' => $weight,
      // Applies from the start of the test run.
      'start_date' => '2017-01-01T00:00:00',
    ]);
    $price_list->save();
    return $price_list;
  }

  /**
   * Adds an item for the variation to a price list.
   */
  protected function addItem(PriceListInterface $price_list, Price $price, int $quantity = 1, ?Price $list_price = NULL): void {
    PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $price_list->id(),
      'purchasable_entity' => $this->variation->id(),
      'quantity' => $quantity,
      'price' => $price,
      'list_price' => $list_price,
    ])->save();
  }

  /**
   * Makes a currency the current one, through the store.
   */
  protected function setCurrency(string $currency_code): void {
    $this->store->setDefaultCurrencyCode($currency_code)->save();
    // The current currency is kept per request.
    $request_stack = $this->container->get('request_stack');
    $request = Request::create('/');
    $request->setSession($request_stack->getCurrentRequest()->getSession());
    $request_stack->push($request);
  }

  /**
   * Resolves the variation's price through the price resolver chain.
   */
  protected function resolve(int $quantity = 1, ?UserInterface $customer = NULL, string $field_name = 'price'): ?Price {
    $context = new Context($customer ?? $this->customer, $this->store, NULL, ['field_name' => $field_name]);
    return $this->container->get('commerce_price.chain_price_resolver')->resolve($this->variation, (string) $quantity, $context);
  }

}
