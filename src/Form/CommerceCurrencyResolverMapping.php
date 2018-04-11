<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;
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
   * Constructs a CommerceCurrencyResolverMapping object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CountryManagerInterface $country_manager) {
    parent::__construct($config_factory);
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('country_manager')
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

    // Get current settings.
    $config = $this->config('commerce_currency_resolver.currency_mapping');
    $currency_mapping = \Drupal::config('commerce_currency_resolver.settings')->get('currency_mapping');
    $currency_default = \Drupal::config('commerce_currency_resolver.settings')->get('currency_default');

    // Get active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();

    $form['matrix'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => t('Currency matrix'),
      '#tree' => TRUE,
      '#weight' => 50,
    ];

    switch ($currency_mapping) {
      case 'lang':
        $languages = CurrencyHelper::getAvailableLanguages();

        $form['matrix']['#open'] = TRUE;

        foreach ($languages as $key => $lang) {
          $form['matrix'][$key] = [
            '#type' => 'radios',
            '#options' => $active_currencies,
            '#title' => $lang,
            '#description' => t('Select currency which should be used with @lang language', ['@lang' => $lang]),
          ];
        }
        break;

      case 'geo':
        $domicile_currency = $config->get('domicile_currency');
        $logic = !empty($config->get('logic')) ? $config->get('logic') : 'country';
        $matrix = $config->get('matrix');

        $form['domicile_currency'] = [
          '#type' => 'checkbox',
          '#default_value' => $domicile_currency,
          '#title' => t('Use domicile currency per country.'),
          '#description' => t('If domicile currency is enabled for specific country it will be considered as primary. Otherwise use default currency defined in settings as fallback.'),
        ];

        $form['logic'] = [
          '#type' => 'select',
          '#default_value' => $logic,
          '#options' => [
            'country' => t('Country'),
            'currency' => t('Currency'),
          ],
          '#title' => t('Matrix logic'),
          '#description' => t('How you want to create matrix. You can assign currency to each country separate, or assign multiple countries to currency'),
        ];

        // Use mapping per country.
        if (empty($domicile_currency)) {

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
                  '#description' => t('Select currency which should be used with @lang language', ['@lang' => $value->render()]),
                  '#default_value' => isset($matrix[$key]) ? $matrix[$key] : $currency_default,
                ];
              }
              break;

            case 'currency':
              foreach ($active_currencies as $key => $currency) {
                $form['matrix'][$key] = [
                  '#type' => 'checkboxes',
                  '#options' => $countries,
                  '#title' => $currency,
                  '#description' => t('Select countires which should be used with @currency currency', ['@currency' => $currency]),
                  //'#default_value' => isset($matrix[$key]) ? $matrix[$key] : $currency_default,
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

    // Set values.
    $config->set('domicile_currency', $form_state->getValue('domicile_currency'))
      ->set('logic', $form_state->getValue('logic'))
      ->set('matrix', $form_state->getValue('matrix'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
