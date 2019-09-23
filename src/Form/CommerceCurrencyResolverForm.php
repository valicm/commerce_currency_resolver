<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceCurrencyResolverForm.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceCurrencyResolverForm extends ConfigFormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $providers;

  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->providers = $this->entityTypeManager->getStorage('commerce_exchange_rates')->loadByProperties(['status' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('config.factory'),
      $container->get('entity_type.manager')
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

    // Get active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();

    $form['currency_mapping'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency mapping'),
      '#description' => $this->t('Select how currency is mapped in the system. By country, language.'),
      '#options' => CurrencyHelper::getAvailableMapping(),
      '#default_value' => $config->get('currency_mapping'),
      '#required' => TRUE,
    ];

    if (!empty(CurrencyHelper::getGeoModules()) && $config->get('currency_mapping') === 'geo') {
      $form['currency_geo'] = [
        '#type' => 'radios',
        '#title' => $this->t('Location module'),
        '#description' => $this->t('Select which module is used for geolocation services'),
        '#options' => CurrencyHelper::getGeoModules(),
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

    $exchange_rates = [];
    foreach ($this->providers as $id => $provider) {
      $exchange_rates[$provider->id()] = $provider->label();
    }

    $form['currency_exchange_rates'] = [
      '#type' => 'select',
      '#title' => $this->t('Exchange rate API'),
      '#description' => $this->t('Select which external service you want to use for calculating exchange rates between currencies'),
      '#options' => $exchange_rates,
      '#default_value' => $config->get('currency_exchange_rates'),
      '#required' => TRUE,
    ];

    if (empty($exchange_rates)) {
      $form['currency_exchange_rates']['#field_suffix'] = $this->t('Please add at least one exchange rate provider under Exchange rates');
    }

    $form['currency_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Default currency'),
      '#description' => $this->t('Select currency which you consider default - as fallback if all resolvers fails, and upon exchange rates should be calculated'),
      '#options' => $active_currencies,
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
