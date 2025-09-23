<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main form.
 */
class CurrencyResolverSettingsForm extends ConfigFormBase {

  /**
   * CurrencyResolverForm constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected TypedConfigManagerInterface $typedConfigManager, protected CurrencyResolverManagerInterface $currencyResolverManager) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('commerce_currency_resolver.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_currency_resolver_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['commerce_currency_resolver.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Get current settings.
    $config = $this->config('commerce_currency_resolver.settings');

    $form['currency_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency source'),
      '#description' => $this->t('Select how currency is selected and calculated.'),
      '#options' => [
        'field' => $this->t('Price field per currency (only applicable to products).'),
      ],
      '#default_value' => $config->get('currency_source') ?? CurrencyResolverManagerInterface::CURRENCY_RESOLVER_PRICE_FIELD,
      '#required' => TRUE,
    ];

    $form['currency_field_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency field prefix'),
      '#description' => $this->t('The prefix on created fields for different currencies. Pattern for dedicated fields per currency is: field_prefix_currency_code. Example, if the prefix is field_price_, field for Euro currency is than field_price_eur'),
      '#default_value' => $config->get('currency_field_prefix') ?? 'field_price_',
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('commerce_currency_resolver.settings');

    // Set values.
    $config->set('currency_source', $form_state->getValue('currency_source'))
      ->set('currency_field_prefix', $form_state->getValue('currency_field_prefix'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
