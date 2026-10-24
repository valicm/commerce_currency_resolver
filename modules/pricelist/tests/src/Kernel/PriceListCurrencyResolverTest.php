<?php

namespace Drupal\Tests\commerce_currency_resolver_pricelist\Kernel;

use Drupal\commerce\Context;
use Drupal\commerce_currency_resolver\Form\CurrencyResolverSettingsForm;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormState;

/**
 * Tests price list prices per currency, with price fields per currency.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver_pricelist\Resolver\PriceListCurrencyResolver
 * @group commerce_currency_resolver
 */
class PriceListCurrencyResolverTest extends PriceListCurrencyKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $base = $this->createPriceList('Base');
    $this->addItem($base, new Price('95', 'USD'));
    $this->addItem($base, new Price('80', 'EUR'), 1, new Price('85', 'EUR'));
    $this->addItem($base, new Price('70', 'EUR'), 10);
    $this->addItem($this->createPriceList('Later', 5), new Price('60', 'EUR'));
  }

  /**
   * Tests that the list item in the current currency wins, by tier.
   *
   * @covers ::resolve
   */
  public function testCurrentCurrency(): void {
    $this->setCurrency('EUR');
    $this->assertEquals(new Price('80', 'EUR'), $this->resolve(), 'The list wins over the euro price field.');
    $this->assertEquals(new Price('80', 'EUR'), $this->resolve(9));
    $this->assertEquals(new Price('70', 'EUR'), $this->resolve(10), 'The largest tier reached.');
    $this->assertEquals(new Price('85', 'EUR'), $this->resolve(1, NULL, 'list_price'));

    $this->setCurrency('USD');
    $this->assertEquals(new Price('95', 'USD'), $this->resolve());
  }

  /**
   * Tests the list weight and the lists that apply to a customer.
   *
   * @covers ::resolve
   */
  public function testListOrder(): void {
    // Commerce Pricelist keeps the lists that apply for the rest of the
    // second, so they are all created first.
    $this->addItem($this->createPriceList('Sale', -1), new Price('65', 'EUR'));
    $this->addItem($this->createPriceList('VIP', -5, ['customers' => [$this->customer->id()]]), new Price('50', 'EUR'));
    $this->setCurrency('EUR');
    $this->assertEquals(new Price('50', 'EUR'), $this->resolve(), 'The lowest weight wins.');
    $this->assertEquals(new Price('65', 'EUR'), $this->resolve(1, $this->createUser()), 'Other customers do not get the customer list.');
  }

  /**
   * Tests that price fields win when the setting is off.
   *
   * @covers ::resolve
   */
  public function testFieldsWin(): void {
    $this->config('commerce_currency_resolver_pricelist.settings')->set('lists_win', FALSE)->save();
    $this->setCurrency('EUR');
    $this->assertEquals(new Price('90', 'EUR'), $this->resolve());

    // Without a field for the currency, the list still applies.
    $this->variation->set('field_price_eur', NULL)->save();
    $this->assertEquals(new Price('80', 'EUR'), $this->resolve());
  }

  /**
   * Tests that no price comes back in a currency other than the current one.
   *
   * @covers \Drupal\commerce_currency_resolver_pricelist\Resolver\CurrencyAwarePriceListResolver::resolve
   */
  public function testOtherCurrency(): void {
    $this->setCurrency('GBP');
    $context = new Context($this->customer, $this->store);
    $this->assertNull($this->container->get('commerce_pricelist.price_resolver')->resolve($this->variation, '1', $context), 'Commerce Pricelist returns no dollar or euro item.');
    $this->assertEquals(new Price('100', 'USD'), $this->resolve(), 'Without a pound price, the variation price as before.');

    $this->setCurrency('EUR');
    $this->assertEquals(new Price('80', 'EUR'), $this->container->get('commerce_pricelist.price_resolver')->resolve($this->variation, '1', $context));
  }

  /**
   * Tests the setting on the currency resolver settings form.
   */
  public function testSettingsForm(): void {
    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->getForm(CurrencyResolverSettingsForm::class);
    $this->assertTrue($form['pricelist_lists_win']['#default_value']);

    $form_state = (new FormState())->setValues([
      'currency_source' => 'field',
      'currency_field_prefix' => 'price_',
      // A programmatic submit unchecks a checkbox with NULL.
      'pricelist_lists_win' => NULL,
      'op' => 'Submit',
    ]);
    $form_builder->submitForm(CurrencyResolverSettingsForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());
    $this->assertFalse($this->config('commerce_currency_resolver_pricelist.settings')->get('lists_win'));
    $this->assertSame('price_', $this->config('commerce_currency_resolver.settings')->get('currency_field_prefix'), 'The main settings are still saved.');
  }

}
