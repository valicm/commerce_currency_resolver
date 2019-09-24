<?php

namespace Drupal\commerce_currency_resolver;

use Symfony\Component\HttpFoundation\RequestStack;
use CommerceGuys\Addressing\Country\CountryRepository;

/**
 * Holds a reference to the currency, resolved on demand.
 */
class CurrentCurrency implements CurrentCurrencyInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Helper service.
   *
   * @var \Drupal\commerce_currency_resolver\CurrencyHelperInterface
   */
  protected $currencyHelper;

  /**
   * Static cache of resolved currency. One per request.
   *
   * @var string
   */
  protected $currency;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack, CurrencyHelperInterface $currency_helper) {
    $this->requestStack = $request_stack;
    $this->currencyHelper = $currency_helper;
    $this->currency = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrency() {
    $request = $this->requestStack->getCurrentRequest();

    if (!$this->currency->contains($request)) {

      // Get default currency from current store.
      $resolved_currency = $this->currencyHelper->defaultCurrencyCode();

      // Go trough each of possible cases.
      switch ($this->currencyHelper->getSourceType()) {
        case 'cookie':

          // Cookie name can be configurable.
          $cookie_name = $this->currencyHelper->getCookieName();

          // Cookie solution check.
          if ($request->cookies->has($cookie_name)) {
            $cookie = $request->cookies->get($cookie_name);

            // Check if cookie value match to any of enabled currencies.
            if (isset($this->currencyHelper->getCurrencies()[$cookie])) {
              $resolved_currency = $cookie;
            }
          }

          break;

        case 'lang':
          // Get current user language.
          $current_language = $this->currencyHelper->currentLanguage();

          // Get mapping language by currency.
          $matrix = $this->currencyHelper->getMappingMatrix();

          if (isset($matrix[$current_language])) {
            $resolved_currency = $matrix[$current_language];
          }

          break;

        case 'geo':
          // Get user country.
          $user_country = $this->currencyHelper->getUserCountry();

          // Get currency matrix data. All mappings are inside.
          // Only if we use domicile currency, mapping is empty.
          $matrix = $this->currencyHelper->getMappingMatrix();

          // We hit some mapping by language or geo and we don't use domicile
          // currency under geo settings.
          if (isset($matrix[$user_country])) {
            $resolved_currency = $matrix[$user_country];
          }

          // If we use, we need to pull currency per country, and
          // check if this currency is enabled. If not use default currency
          // defined in admin.
          if (!empty($this->currencyHelper->getDomicileCurrency())) {
            $country_repository = new CountryRepository();
            $currency_code = $country_repository->get($user_country)->getCurrencyCode();

            // Check if domicile currency is among enabled currencies.
            if (isset($this->currencyHelper->getCurrencies()[$currency_code])) {
              $resolved_currency = $currency_code;
            }
          }

          break;

        // Do nothing. Already is set currency from resolved Store.
        default:
      }

      $this->currency[$request] = $resolved_currency;
    }

    return $this->currency[$request];
  }

}
