<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\commerce_currency_resolver\CurrencyHelperInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceCurrencyResolverForm.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceCurrencyResolverForm extends ConfigFormBase {

  /**
   * Helper service.
   *
   * @var \Drupal\commerce_currency_resolver\CurrencyHelperInterface
   */
  protected $currencyHelper;

  /**
   * CommerceCurrencyResolverForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config.
   * @param \Drupal\commerce_currency_resolver\CurrencyHelperInterface $currency_helper
   *   Generic helper.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CurrencyHelperInterface $currency_helper) {
    parent::__construct($config_factory);
    $this->currencyHelper = $currency_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('config.factory'),
      $container->get('commerce_currency_resolver.currency_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_currency_resolver_admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_currency_resolver.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get current settings.
    $config = $this->config('commerce_currency_resolver.settings');

    $form['currency_mapping'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency mapping'),
      '#description' => $this->t('Select how currency is mapped in the system. By country, language.'),
      '#options' => $this->getAvailableMapping(),
      '#default_value' => $config->get('currency_mapping'),
      '#required' => TRUE,
    ];

    $geo_modules = $this->currencyHelper->getGeoModules();

    if (!empty($geo_modules) && $config->get('currency_mapping') === 'geo') {
      $form['currency_geo'] = [
        '#type' => 'radios',
        '#title' => $this->t('Location module'),
        '#description' => $this->t('Select which module is used for geolocation services'),
        '#options' => $geo_modules,
        '#default_value' => $config->get('currency_geo'),
        '#required' => TRUE,
      ];
    }

    $form['currency_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency source'),
      '#description' => $this->t('Select how currency is added and calculated. To avoid possible errors, "Combo mode" is best option. If field for currency does not exist, it will fallback to automatic conversion for this specific currency'),
      '#options' => [
        'auto' => $this->t('Automatic conversion'),
        'field' => $this->t('Price field per currency'),
        'combo' => $this->t('Combo mode'),
      ],
      '#default_value' => $config->get('currency_source'),
      '#required' => TRUE,
    ];

    $exchange_rates = $this->currencyHelper->getExchangeRatesProviders();

    $form['currency_exchange_rates'] = [
      '#type' => 'select',
      '#title' => $this->t('Exchange rate API'),
      '#description' => $this->t('Select which external service you want to use for calculating exchange rates between currencies'),
      '#options' => $exchange_rates,
      '#default_value' => $config->get('currency_exchange_rates'),
      '#required' => TRUE,
    ];

    // If there is no exchanger plugin.
    if (empty($exchange_rates)) {
      $form['currency_exchange_rates']['#field_suffix'] = $this->t('Please add at least one exchange rate provider under Exchange rates');
      $form['submit']['#disabled'] = TRUE;
    }

    $form['currency_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Default currency'),
      '#description' => $this->t('Select currency which you consider default - as fallback if all resolvers fails, and upon exchange rates should be calculated'),
      '#options' => $this->currencyHelper->getCurrencies(),
      '#default_value' => $config->get('currency_default'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Get all available option where currency could be mapped.
   *
   * @return array
   *   Array of options available for currency mapping.
   */
  protected function getAvailableMapping() {
    $mapping = [];

    // Default store.
    $mapping['store'] = $this->t('Store (default Commerce 2 behavior)');
    $mapping['cookie'] = $this->t('Cookie (currency block selector)');

    // Check if exist geo based modules.
    // We support for now two of them.
    if (!empty($this->currencyHelper->getGeoModules())) {
      $mapping['geo'] = $this->t('By Country');
    }

    // Check if the site is multilingual.
    if (count($this->currencyHelper->getLanguages()) > 1) {
      $mapping['lang'] = $this->t('By Language');
    }

    return $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_currency_resolver.settings');

    // Set values.
    $config->set('currency_mapping', $form_state->getValue('currency_mapping'))
      ->set('currency_geo', $form_state->getValue('currency_geo'))
      ->set('currency_source', $form_state->getValue('currency_source'))
      ->set('currency_default', $form_state->getValue('currency_default'))
      ->set('currency_exchange_rates', $form_state->getValue('currency_exchange_rates'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
