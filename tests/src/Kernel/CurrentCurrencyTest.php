<?php

namespace Drupal\Tests\commerce_currency_resolver\Kernel;

use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests current currency class.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver\CurrentCurrency
 * @group commerce_currency_resolver
 */
class CurrentCurrencyTest extends OrderKernelTestBase {

  use CurrentCurrencyTrait;

  /**
   * The current currency.
   *
   * @var \Drupal\commerce_currency_resolver\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The language default.
   *
   * @var \Drupal\Core\Language\LanguageDefault
   */
  protected $languageDefault;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'language',
    'language_test',
    'system',
    'commerce_exchanger',
    'commerce_currency_resolver',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    $this->installConfig('system');
    $this->installConfig('language');

    // Add additional language.
    ConfigurableLanguage::create(['id' => 'hr'])->save();

    // Ensure we are building a new Language object for each test.
    $this->languageManager = $this->container->get('language_manager');
    $this->languageDefault = $this->container->get('language.default');
    $language = ConfigurableLanguage::load('hr');
    $this->languageDefault->set($language);
    $this->config('system.site')->set('default_langcode', $language->getId())->save();
    $this->languageManager->reset();

    // Add additional currency.
    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('HRK');

    // Resolver configuration specifics.
    $this->installConfig(['commerce_currency_resolver']);
    $this->config('commerce_currency_resolver.settings')
      ->set('currency_default', 'HRK')->save();
    $this->currentCurrency = $this->container->get('commerce_currency_resolver.current_currency');

    // Prepare mapping for language test.
    $this->config('commerce_currency_resolver.currency_mapping')->setData([
      'domicile_currency' => NULL,
      'logic' => NULL,
      'matrix' => [
        'en' => 'USD',
        'hr' => 'HRK',
      ],

    ])->save();

  }

  /**
   * Test current currency.
   *
   * @covers ::getCurrency
   */
  public function testDefault() {
    $this->assertInstanceOf(StoreInterface::class, $this->store);
    $this->assertEquals($this->store->getDefaultCurrencyCode(), $this->currentCurrency->getCurrency());
    $this->assertEquals('USD', $this->store->getDefaultCurrencyCode());
    $this->assertEquals('USD', $this->currentCurrency->getCurrency());
    $this->assertEquals('HRK', $this->config('commerce_currency_resolver.settings')
      ->get('currency_default'));
  }

  /**
   * Tests current currency with no store.
   *
   * @covers ::getCurrency
   */
  public function testNoStore() {
    $this->assertEquals('HRK', $this->config('commerce_currency_resolver.settings')
      ->get('currency_default'));
    $this->assertEquals('USD', $this->store->getDefaultCurrencyCode());
    $this->assertEquals('USD', $this->currentCurrency->getCurrency());
    $this->assertEquals(1, $this->store->id());
    $store = Store::load($this->store->id());
    $store->delete();
    $this->assertEmpty(Store::load(1));
    $this->resetCurrencyContainer();
    $this->assertEquals('HRK', $this->currentCurrency->getCurrency());
  }

  /**
   * Tests language based current currency.
   *
   * @covers ::getCurrency
   */
  public function testLanguage() {
    $this->assertEquals('USD', $this->currentCurrency->getCurrency());

    // Change mapping from store to language.
    $this->config('commerce_currency_resolver.settings')
      ->set('currency_mapping', 'lang')->save();

    // Validate default language.
    $this->assertEquals('hr', $this->languageManager->getCurrentLanguage()->getId());

    // Rebuild container and recheck currency.
    $this->resetCurrencyContainer();
    $this->assertEquals('HRK', $this->currentCurrency->getCurrency());

    // Change language back to english.
    $this->config('system.site')->set('default_langcode', 'en')->save();
    $this->resetCurrencyContainer();
    $this->assertEquals('USD', $this->currentCurrency->getCurrency());
  }

}
