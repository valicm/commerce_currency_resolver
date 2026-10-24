<?php

namespace Drupal\commerce_currency_resolver_pricelist\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for commerce_currency_resolver_pricelist.
 */
class PricelistHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new PricelistHooks object.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, $view_mode): void {
    // Prices from price lists differ per currency.
    if (isset($build['#commerce_product']) || isset($build['#commerce_product_variation'])) {
      $build['#cache']['contexts'][] = 'currency_resolver';
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'commerce_currency_resolver_settings'.
   */
  #[Hook('form_commerce_currency_resolver_settings_alter')]
  public function formCommerceCurrencyResolverSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $form['pricelist_lists_win'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Price lists win over currency fields'),
      '#description' => $this->t('A price list item in the current currency is used before the price field for that currency. Without it, the price field wins and price lists apply only where a product has no price field for the currency.'),
      '#default_value' => $this->configFactory->get('commerce_currency_resolver_pricelist.settings')->get('lists_win') ?? TRUE,
      '#weight' => 5,
    ];
    // The exchanger submodule gives the button its own handlers, which replace
    // the form's; otherwise the form's handlers run.
    if (isset($form['submit']['#submit'])) {
      $form['submit']['#submit'][] = [static::class, 'submitSettings'];
    }
    else {
      $form['#submit'][] = [static::class, 'submitSettings'];
    }
  }

  /**
   * Submission handler for the currency resolver settings form.
   */
  public static function submitSettings(array &$form, FormStateInterface $form_state): void {
    \Drupal::configFactory()->getEditable('commerce_currency_resolver_pricelist.settings')
      ->set('lists_win', (bool) $form_state->getValue('pricelist_lists_win'))
      ->save();
  }

}
