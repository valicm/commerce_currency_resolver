<?php

namespace Drupal\commerce_currency_resolver_language\Resolver;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Resolver\CurrencyResolverInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Returns the currency by user current language.
 */
class CurrencyResolverLanguage implements CurrencyResolverInterface {

  /**
   * Constructs a new StoreCurrencyResolver object.
   */
  public function __construct(protected LanguageManagerInterface $languageManager, protected ConfigFactoryInterface $configFactory, protected CurrencyResolverManagerInterface $currencyResolverManager) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?CurrencyInterface {
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    if ($current_language) {
      $matrix = $this->configFactory->get('commerce_currency_resolver_language.currency_mapping')->get('matrix');
      if (isset($matrix[$current_language])) {
        return $this->currencyResolverManager->getCurrencyByCode($matrix[$current_language]);
      }
    }

    return NULL;
  }

}
