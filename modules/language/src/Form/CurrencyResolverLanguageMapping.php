<?php

namespace Drupal\commerce_currency_resolver_language\Form;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mapping form.
 */
class CurrencyResolverLanguageMapping extends ConfigFormBase {

  /**
   * Constructs a CurrencyResolverLanguageMapping object.
   */
  public function __construct(ConfigFactoryInterface $configFactory, TypedConfigManagerInterface $typed_config_manager, protected CountryManagerInterface $countryManager, protected CurrencyResolverManagerInterface $currencyResolverManager, protected LanguageManagerInterface $languageManager) {
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
      $container->get('commerce_currency_resolver.manager'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_currency_resolver_language_currency_mapping';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['commerce_currency_resolver_language.currency_mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Get current settings.
    $matrix = $this->config('commerce_currency_resolver_language.currency_mapping')->get('matrix') ?? [];

    // Get active currencies.
    $active_currencies = $this->currencyResolverManager->getCurrencies();

    $options = [];
    foreach ($active_currencies as $currency) {
      $options[$currency->getCurrencyCode()] = $currency->label();
    }

    $languages = $this->languageManager->getLanguages();

    $form['matrix'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Currency matrix'),
      '#tree' => TRUE,
      '#weight' => 50,
    ];

    foreach ($languages as $key => $language) {
      $form['matrix'][$language->getId()] = [
        '#type' => 'radios',
        '#options' => $options,
        '#default_value' => $matrix[$key] ?? NULL,
        '#title' => $language->getName(),
        '#description' => $this->t('Select currency which should be used with @lang language', ['@lang' => $language->getName()]),
      ];
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
    $config = $this->config('commerce_currency_resolver_language.currency_mapping');

    $matrix = $form_state->getValue('matrix');

    // Set values.
    $config->set('matrix', $matrix)->save();

    parent::submitForm($form, $form_state);
  }

}
