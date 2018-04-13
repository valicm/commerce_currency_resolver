<?php
namespace Drupal\commerce_currency_resolver\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_currency_resolver\CurrencyHelper;
use CommerceGuys\Intl\Country\CountryRepository;

/**
 * Returns a price and currency depending of language or country.
 */
class CommerceCurrencyResolver implements PriceResolverInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new CommerceCurrencyResolver object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {

    // Get main currency settings.
    $settings = \Drupal::config('commerce_currency_resolver.settings');

    // Get how currency should be mapped.
    $mapping = $settings->get('currency_mapping');

    // Get default commerce field price currency and amount.
    $field_currency = $entity->getPrice()->getCurrencyCode();
    $field_amount = $entity->getPrice()->getNumber();

    // Enabled currencies.
    $enabled_currencies = CurrencyHelper::getEnabledCurrency();

    // Skip if mapping is none.
    if (!empty($mapping)) {
      $currency_mapping = \Drupal::config('commerce_currency_resolver.currency_mapping');

      // Get currency matrix data. All mappings are inside.
      $matrix = $currency_mapping->get('matrix');

      // Target currency default.
      $convert_to = FALSE;

      // Check if matrix exist.
      if (isset($matrix)) {

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

            // See if we use domicile currency per country.
            $domicile_currency = $currency_mapping->get('domicile_currency');

            // If we use, we need to pull currency per country, and
            // check if this currency is enabled. If not use default currency
            // defined in admin.
            if (!empty($domicile_currency)) {
              $country_repository = new CountryRepository();
              $country = $country_repository->get($current);
              $currency_code = $country->getCurrencyCode();

              // If domicile currency is enabled show it. Otherwise use default.
              if (isset($enabled_currencies[$currency_code])) {
                $convert_to = $currency_code;
              }

              else {
                $convert_to = $settings->get('currency_default');
              }

            }
            break;
        }

        // We hit some mapping by language or geo and we don't have already
        // set convert_to variable. Check for convert_to is added for specific
        // case of using domicile currency in geo targeting. There already
        // we have set convert_to variable.
        if (isset($matrix[$current]) && !$convert_to) {
          $convert_to = $matrix[$current];
        }

        // Convert if we have mapping request. Target currency different
        // then field currency.
        if ($convert_to && $field_currency !== $convert_to) {
          $currency_source = $settings->get('currency_source');

          switch ($currency_source) {
            case 'combo':
            case 'field':

              // Check if we have field.
              if ($entity->hasField('field_price_' . strtolower($convert_to))) {
                $price = $entity->get('field_price_' . strtolower($convert_to))
                  ->getValue();
                $price = reset($price);

                // Return price.
                return new Price($price['number'], $price['currency_code']);
              }

              else {
                // Calculate conversion for the combo mode if field does not
                // exist.
                if ($currency_source === 'combo') {
                  // Run auto.
                }
              }
              break;

            default:
            case 'auto':
              // Calculate conversion.
              break;

          }
        }

      }
    }
  }

}
