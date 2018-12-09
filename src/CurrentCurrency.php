<?php

namespace Drupal\commerce_currency_resolver;

use Symfony\Component\HttpFoundation\RequestStack;
use CommerceGuys\Addressing\Country\CountryRepository;

/**
 * Holds a reference to the currency, resolved on demand.
 */
class CurrentCurrency implements CurrentCurrencyInterface {

  use CommerceCurrencyResolverTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Static cache of resolved currency. One per request.
   *
   * @var string
   */
  protected $currency;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
    $this->currency = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrency() {
    $request = $this->requestStack->getCurrentRequest();

    if (!$this->currency->contains($request)) {

      // Get default currency from current store.
      $resolved_currency = $this->defaultCurrencyCode();

      // Go trough each of possible cases.
      switch ($this->getSourceType()) {
        case 'cookie':
          // Cookie solution check.
          if ($request->cookies->has('commerce_currency')) {
            $cookie = $request->cookies->get('commerce_currency');

            // Check if cookie value match to any of enabled currencies.
            if (isset($this->getEnabledCurrencies()[$cookie])) {
              $resolved_currency = $cookie;
            }
          }

          break;

        case 'lang':
          // Get current user language.
          $current_language = \Drupal::service('language_manager')->getCurrentLanguage()->getId();

          // Get mapping language by currency.
          $matrix = \Drupal::config('commerce_currency_resolver.currency_mapping')->get('matrix');

          if (isset($matrix[$current_language])) {
            $resolved_currency = $matrix[$current_language];
          }

          break;

        case 'geo':
          // Get user country.
          $user_country = $this->getUserCountry();

          $currency_mapping = \Drupal::config('commerce_currency_resolver.currency_mapping');

          // Get currency matrix data. All mappings are inside.
          // Only if we use domicile currency, mapping is empty.
          $matrix = $currency_mapping->get('matrix') ?? [];

          // We hit some mapping by language or geo and we don't use domicile
          // currency under geo settings.
          if (isset($matrix[$user_country])) {
            $resolved_currency = $matrix[$user_country];
          }

          // If we use, we need to pull currency per country, and
          // check if this currency is enabled. If not use default currency
          // defined in admin.
          if (!empty($currency_mapping->get('domicile_currency'))) {
            $country_repository = new CountryRepository();
            $currency_code = $country_repository->get($user_country)->getCurrencyCode();

            // Check if domicile currency is among enabled currencies.
            if (isset($this->getEnabledCurrencies()[$currency_code])) {
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
