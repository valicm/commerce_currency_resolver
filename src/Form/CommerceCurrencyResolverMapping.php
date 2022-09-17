<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\commerce_currency_resolver\CurrencyHelperInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class CommerceCurrencyResolverMapping.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceCurrencyResolverMapping extends ConfigFormBase {

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * Helper service.
   *
   * @var \Drupal\commerce_currency_resolver\CurrencyHelperInterface
   */
  protected $currencyHelper;

  /**
   * Constructs a CommerceCurrencyResolverMapping object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager.
   * @param \Drupal\commerce_currency_resolver\CurrencyHelperInterface $currencyHelper
   *   Currency helper.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CountryManagerInterface $country_manager, CurrencyHelperInterface $currencyHelper) {
    parent::__construct($config_factory);
    $this->countryManager = $country_manager;
    $this->currencyHelper = $currencyHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('country_manager'),
      $container->get('commerce_currency_resolver.currency_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_currency_resolver_admin_mapping';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_currency_resolver.currency_mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get type of mapping for the resolver.
    $currency_mapping = $this->config('commerce_currency_resolver.settings')->get('currency_mapping');

    // Get default currency.
    $currency_default = $this->config('commerce_currency_resolver.settings')->get('currency_default');

    // Get current settings.
    $config = $this->config('commerce_currency_resolver.currency_mapping');

    // Get active currencies.
    $active_currencies = $this->currencyHelper->getCurrencies();

    // Get mapped currency.
    $matrix = $config->get('matrix');

    switch ($currency_mapping) {
      case 'lang':
        $languages = $this->currencyHelper->getLanguages();

        $form['matrix'] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $this->t('Currency matrix'),
          '#tree' => TRUE,
          '#weight' => 50,
        ];

        foreach ($languages as $key => $language) {
          $form['matrix'][$key] = [
            '#type' => 'radios',
            '#options' => $active_currencies,
            '#default_value' => $matrix[$key] ?? $currency_default,
            '#title' => $language,
            '#description' => $this->t('Select currency which should be used with @lang language', ['@lang' => $language]),
          ];
        }
        break;

      case 'geo':
        $domicile_currency = $config->get('domicile_currency');
        $logic = !empty($config->get('logic')) ? $config->get('logic') : 'country';

        $form['domicile_currency'] = [
          '#type' => 'checkbox',
          '#default_value' => $domicile_currency,
          '#title' => $this->t('Use domicile currency per country.'),
          '#description' => $this->t('If domicile currency is enabled for specific country, it will be considered as primary currency. Otherwise currency resolver use default currency defined in settings as fallback.'),
        ];

        // Use mapping per country.
        if (empty($domicile_currency)) {

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

          // Country list. Get country list which is already processed with
          // alters instead of taking static list.
          $countries = $this->countryManager->getList();

          switch ($logic) {
            default:
            case 'country':
              foreach ($countries as $key => $value) {
                $form['matrix'][$key] = [
                  '#type' => 'select',
                  '#options' => $active_currencies,
                  '#title' => $value->render(),
                  '#description' => $this->t('Select currency which should be used with @lang language', ['@lang' => $value->render()]),
                  '#default_value' => $matrix[$key] ?? $currency_default,
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
                  '#title' => $currency,
                  '#description' => $this->t('Select countires which should be used with @currency currency', ['@currency' => $currency]),
                  '#default_value' => isset($data[$key]) ? $data[$key] : '',
                ];
              }

              break;
          }

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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_currency_resolver.currency_mapping');

    // Get matrix logic value.
    $logic = $form_state->getValue('logic');

    // Process results in some cases.
    // We want to have same array in any type of currency matrix.
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
    $config->set('domicile_currency', $form_state->getValue('domicile_currency'))
      ->set('logic', $logic)
      ->set('matrix', $matrix)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
