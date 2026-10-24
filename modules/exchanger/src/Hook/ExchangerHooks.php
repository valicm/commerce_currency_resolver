<?php

namespace Drupal\commerce_currency_resolver_exchanger\Hook;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\Condition\OrderTotalPrice;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\Fee\OrderFixedAmount;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\Fee\OrderItemFixedAmount;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\PromotionOffer\OrderFixedAmountOff;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\PromotionOffer\OrderItemFixedAmountOff;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for commerce_currency_resolver_exchanger.
 */
class ExchangerHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new ExchangerHooks object.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Implements hook_commerce_promotion_offer_info_alter().
   */
  #[Hook('commerce_promotion_offer_info_alter')]
  public function commercePromotionOfferInfoAlter(array &$definitions): void {
    $definitions['order_item_fixed_amount_off']['class'] = OrderItemFixedAmountOff::class;
    $definitions['order_fixed_amount_off']['class'] = OrderFixedAmountOff::class;
  }

  /**
   * Implements hook_commerce_fee_info_alter().
   */
  #[Hook('commerce_fee_info_alter')]
  public function commerceFeeInfoAlter(array &$definitions): void {
    $definitions['order_item_fixed_amount']['class'] = OrderItemFixedAmount::class;
    $definitions['order_fixed_amount']['class'] = OrderFixedAmount::class;
  }

  /**
   * Implements hook_commerce_condition_info_alter().
   */
  #[Hook('commerce_condition_info_alter')]
  public function commerceConditionInfoAlter(array &$definitions): void {
    $definitions['order_total_price']['class'] = OrderTotalPrice::class;
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'commerce_currency_resolver_settings'.
   */
  #[Hook('form_commerce_currency_resolver_settings_alter')]
  public function formCommerceCurrencyResolverSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->get('commerce_currency_resolver.settings');

    $form['currency_source']['#options'] = [
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_AUTO => $this->t('Automatic conversion'),
      CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_COMBO => $this->t('Combo mode - price per field and automatic'),
    ];

    $form['currency_source']['#description'] .= $this->t('It works with price field per currency for products. With exchanger submodule it is not possible to use only price per field option');

    /** @var \Drupal\commerce_exchanger\Entity\ExchangeRatesInterface[] $providers */
    $providers = $this->entityTypeManager->getStorage('commerce_exchange_rates')->loadMultiple();

    $exchange_rates = [];
    foreach ($providers as $provider) {
      if ($provider->status()) {
        $exchange_rates[$provider->id()] = $provider->label();
      }
    }
    $form['currency_exchange_rates'] = [
      '#type' => 'select',
      '#title' => $this->t('Exchange rate API'),
      '#description' => $this->t('Select which external service you want to use for calculating exchange rates between currencies'),
      '#options' => $exchange_rates,
      '#default_value' => $config->get('currency_exchange_rates'),
      '#required' => TRUE,
      '#weight' => 0,
    ];

    // If there is no exchanger plugin.
    if (empty($exchange_rates)) {
      $form['currency_exchange_rates']['#field_suffix'] = $this->t('Please add at least one exchange rate provider under Exchange rates');
      $form['submit']['#disabled'] = TRUE;
    }

    $form['submit']['#submit'][] = [static::class, 'submitSettings'];
  }

  /**
   * Submission handler for the currency resolver settings form.
   */
  public static function submitSettings(array &$form, FormStateInterface $form_state): void {
    $config = \Drupal::configFactory()->getEditable('commerce_currency_resolver.settings');

    // Set values.
    $config->set('currency_source', $form_state->getValue('currency_source'))
      ->set('currency_field_prefix', $form_state->getValue('currency_field_prefix'))
      ->set('currency_exchange_rates', $form_state->getValue('currency_exchange_rates'))
      ->save();
  }

}
