<?php

namespace Drupal\Tests\commerce_currency_resolver_exchanger\Kernel;

use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\commerce_currency_resolver\Traits\CurrentCurrencyTrait;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests current currency language class.
 *
 * @coversDefaultClass \Drupal\commerce_currency_resolver_language\Resolver\CurrencyResolverLanguage
 * @group commerce_currency_resolver
 */
class CurrentLanguageCurrencyTest extends OrderKernelTestBase {

  use CurrentCurrencyTrait;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The language default.
   */
  protected LanguageDefault $languageDefault;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'language',
    'language_test',
    'system',
    'commerce_currency_resolver',
    'commerce_currency_resolver_language',
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
    $currency_importer->import('EUR');

    // Resolver configuration specifics.
    $this->installConfig(['commerce_currency_resolver']);
    $this->currentCurrency = $this->container->get('commerce_price.current_currency');

    // Prepare mapping for language test.
    $this->config('commerce_currency_resolver_language.currency_mapping')->setData([
      'matrix' => [
        'en' => 'USD',
        'hr' => 'EUR',
      ],

    ])->save();

  }

  /**
   * Test the current currency.
   *
   * @covers ::resolve
   */
  public function testDefault(): void {
    $this->assertEquals('hr', $this->languageManager->getDefaultLanguage()->getId());
    $this->assertInstanceOf(StoreInterface::class, $this->store);
    $this->assertEquals('EUR', $this->currentCurrency->getCurrency()->getCurrencyCode());
    $this->assertEquals('USD', $this->store->getDefaultCurrencyCode());
  }

  /**
   * Tests current currency with no store.
   *
   * @covers ::resolve
   */
  public function testNoStore(): void {
    $this->assertEquals('USD', $this->store->getDefaultCurrencyCode());
    $this->assertEquals('EUR', $this->currentCurrency->getCurrency()->getCurrencyCode());
    $this->assertEquals(1, $this->store->id());
    $store = Store::load($this->store->id());
    $store->delete();
    $this->assertEmpty(Store::load(1));
    // We resolve now currency per language, not store.
    // so even we don't have store, the currency is present there.
    $this->assertEquals('EUR', $this->currentCurrency->getCurrency()?->getCurrencyCode());
  }

  /**
   * Tests language-based current currency.
   *
   * @covers ::resolve
   */
  public function testLanguage(): void {
    $this->assertEquals('EUR', $this->currentCurrency->getCurrency()->getCurrencyCode());

    // Validate default language.
    $this->assertEquals('hr', $this->languageManager->getCurrentLanguage()->getId());

    // Rebuild container and recheck currency.
    $this->assertEquals('EUR', $this->currentCurrency->getCurrency()->getCurrencyCode());

    // Change language back to english.
    $language = ConfigurableLanguage::load('en');
    $this->languageDefault->set($language);
    $this->config('system.site')->set('default_langcode', 'en')->save();
    $this->resetCurrencyContainer();
    $this->assertEquals('USD', $this->currentCurrency->getCurrency()->getCurrencyCode());
  }

}
