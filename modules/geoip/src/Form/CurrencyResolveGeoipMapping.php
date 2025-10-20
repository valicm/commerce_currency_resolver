<?php

namespace Drupal\commerce_currency_resolver_geoip\Form;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mapping form.
 */
class CurrencyResolveGeoipMapping extends ConfigFormBase {

  /**
   * Constructs a CurrencyResolveGeoipMapping object.
   */
  public function __construct(ConfigFactoryInterface $configFactory, TypedConfigManagerInterface $typed_config_manager, protected CountryManagerInterface $countryManager, protected CurrencyResolverManagerInterface $currencyResolverManager) {
    parent::__construct($configFactory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('country_manager'),
      $container->get('commerce_currency_resolver.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_currency_resolver_geoip_currency_mapping';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['commerce_currency_resolver_geoip.currency_mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get current settings.
    $config = $this->config('commerce_currency_resolver_geoip.currency_mapping');

    // Get active currencies.
    $active_currencies = $this->currencyResolverManager->getCurrencies();

    // Get mapped currency.
    $matrix = $config->get('matrix');

    $logic = $config->get('logic') ?: 'currency';

    $options = [];
    foreach ($active_currencies as $currency) {
      $options[$currency->getCurrencyCode()] = $currency->label();
    }

    $form['logic'] = [
      '#type' => 'select',
      '#default_value' => $logic,
      '#options' => [
        'country' => $this->t('Country'),
        'currency' => $this->t('Currency'),
      ],
      '#title' => $this->t('Matrix logic'),
      '#description' => $this->t('How you want to create matrix. You can assign currency to each country separate, or assign multiple countries to each currency'),
    ];

    $form['matrix'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Currency matrix'),
      '#tree' => TRUE,
      '#weight' => 50,
    ];

    // Country list. Get a country list which is already processed with
    // alters instead of taking a static list.
    $countries = $this->countryManager->getList();

    switch ($logic) {
      default:
      case 'country':
        foreach ($countries as $key => $value) {
          $form['matrix'][$key] = [
            '#type' => 'select',
            '#options' => $options,
            '#title' => $value->render(),
            '#required' => TRUE,
            '#description' => $this->t('Select currency which should be used with @lang language', ['@lang' => $value->render()]),
            '#default_value' => $matrix[$key] ?? NULL,
          ];
        }
        break;

      case 'currency':
        $data = [];

        // Process and reverse existing config from country->currency
        // to currency -> countries list for autocomplete fields.
        if (!empty($matrix)) {
          foreach ($matrix as $country => $currency) {
            if (!isset($data[$currency])) {
              $data[$currency] = $country;
            }
            else {
              $data[$currency] .= ', ' . $country;
            }
          }
        }

        // Render autocomplete fields for each currency.
        foreach ($active_currencies as $key => $currency) {
          $form['matrix'][$key] = [
            '#type' => 'textfield',
            '#autocomplete_route_name' => 'commerce_currency_resolver.countries.autocomplete',
            '#title' => $currency->label(),
            '#required' => TRUE,
            '#description' => $this->t('Select countries which should be used with @currency currency', ['@currency' => $key]),
            '#default_value' => $data[$key] ?? '',
          ];
        }

        break;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('commerce_currency_resolver_geoip.currency_mapping');

    // Get matrix logic value.
    $logic = $form_state->getValue('logic');

    // Process results in some cases.
    // We want to have the same array in any type of currency matrix.
    if ($logic === 'currency') {
      $raw_data = $form_state->getValue('matrix');
      $matrix = [];
      foreach ($raw_data as $currency => $list) {
        $countries = explode(',', $list);

        foreach ($countries as $country) {
          $matrix[trim($country)] = $currency;
        }
      }
    }

    else {
      $matrix = $form_state->getValue('matrix');
    }

    // Set values.
    $config->set('logic', $logic)
      ->set('matrix', $matrix)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
