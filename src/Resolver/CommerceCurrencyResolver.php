<?php
namespace Drupal\commerce_currency_resolver\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_currency_resolver\CurrencyHelper;

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

    // Skip if mapping is none.
    if (!empty($mapping)) {
      $currency_mapping = \Drupal::config('commerce_currency_resolver.currency_mapping');
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
            $current = 'US';
            break;
        }

        // We hit some mapping by language or geo.
        if (isset($matrix[$current])) {
          $convert_to = $matrix[$current];
        }

        // Convert if we have mapping request. Target currency different
        // then field currency.
        if ($field_currency !== $convert_to) {
          $currency_source = $settings->get('currency_source');

          switch ($currency_source) {
            case 'combo':
            case 'field':

              // Check if we have field.
              if ($entity->hasField('field_price_' . strtolower($convert_to))) {
                $price = $entity->get('field_price_' . strtolower($convert_to))->getValue();
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
