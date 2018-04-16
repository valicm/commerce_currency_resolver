<?php

namespace Drupal\commerce_currency_resolver;

use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Language\LanguageManagerInterface;
use CommerceGuys\Intl\Country\CountryRepository;

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
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Static cache of resolved currency. One per request.
   *
   * @var string
   */
  protected $currency;

  /**
   * {@inheritdoc}
   */
  public function __construct(LanguageManagerInterface $language_manager, RequestStack $request_stack) {
    $this->languageManager = $language_manager;
    $this->requestStack = $request_stack;
    $this->currency = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrency() {
    $request = $this->requestStack->getCurrentRequest();

    if (!$this->currency->contains($request)) {

      // Get main currency settings.
      $settings = \Drupal::config('commerce_currency_resolver.settings');

      // Get how currency should be mapped.
      $mapping = $settings->get('currency_mapping');

      // Enabled currencies.
      $enabled_currencies = CurrencyHelper::getEnabledCurrency();

      // Target currency default.
      $resolved_currency = $settings->get('currency_default');

      // Skip if mapping is none.
      if (!empty($mapping)) {
        $currency_mapping = \Drupal::config('commerce_currency_resolver.currency_mapping');

        // See if we use domicile currency per country.
        $domicile_currency = $currency_mapping->get('domicile_currency');

        // Get currency matrix data. All mappings are inside.
        // Only if we use domicile currency, mapping is empty.
        $matrix = $currency_mapping->get('matrix');

        // Check if matrix exist.
        if (isset($matrix) || !empty($domicile_currency)) {

          // Process by mapping type.
          switch ($mapping) {
            case 'lang';
              // Get current language.
              $current = $this->languageManager->getCurrentLanguage()->getId();
              break;

            default:
            case 'geo':
              // Get user country.
              $geo_service = $settings->get('currency_geo');
              $current = CurrencyHelper::getUserCountry($geo_service);

              // If we use, we need to pull currency per country, and
              // check if this currency is enabled. If not use default currency
              // defined in admin.
              if (!empty($domicile_currency)) {
                $country_repository = new CountryRepository();
                $country = $country_repository->get($current);
                $currency_code = $country->getCurrencyCode();

                // If domicile currency is enabled show it.
                // Otherwise use default.
                if (isset($enabled_currencies[$currency_code])) {
                  $resolved_currency = $currency_code;
                }

              }
              break;
          }

          // We hit some mapping by language or geo and we don't use domicile
          // currency under geo settings.
          if (isset($matrix[$current]) && empty($domicile_currency)) {
            $resolved_currency = $matrix[$current];
          }

        }
      }

      else {
        // Cookie solution check.
        if ($request->cookies->has('commerce_currency')) {
          $cookie = $request->cookies->get('commerce_currency');

          // Check if value is among enabled currencies.
          if (isset($enabled_currencies[$cookie])) {
            $resolved_currency = $cookie;
          }

        }
      }
      $this->currency[$request] = $resolved_currency;
    }

    return $this->currency[$request];
  }

}
