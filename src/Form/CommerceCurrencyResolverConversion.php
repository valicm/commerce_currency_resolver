<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;
use Drupal\commerce_currency_resolver\Event\ExchangeImport;
use Drupal\commerce_currency_resolver\Event\CommerceCurrencyResolverEvents;

/**
 * Class CommerceCurrencyResolverConversion.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceCurrencyResolverConversion extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_currency_resolver_admin_conversion';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_currency_resolver.currency_conversion'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get current settings.
    $config = $this->config('commerce_currency_resolver.currency_conversion');

    // Get active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();

    // Cross sync settings.
    $cross_sync = $config->get('use_cross_sync');

    // Get our default currency.
    $currency_default = \Drupal::config('commerce_currency_resolver.settings')
      ->get('currency_default');

    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Exchange rate API'),
      '#description' => $this->t('Select which external service you want to use for calculating exchange rates between currencies'),
      '#options' => CurrencyHelper::getExchangeServices(),
      '#default_value' => $config->get('source'),
      '#required' => TRUE,
    ];

    $source_readable = CurrencyHelper::getExchangeServices()[$config->get('source')];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key', ['@source' => $source_readable]),
      '#description' => $this->t('Enter API key. ECB does not require API key'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['cron'] = [
      '#type' => 'select',
      '#title' => $this->t('Exchange rate cron'),
      '#description' => $this->t('Select how often exchange rates should be imported. Note about EBC, they update exchange rates once a day'),
      '#options' => [
        60 * 60 * 6 => '6 hours',
        60 * 60 * 12 => '12 hours',
        60 * 60 * 24 => 'Once a day',
      ],
      '#default_value' => (int) $config->get('cron'),
      '#required' => TRUE,
    ];

    $form['use_cross_sync'] = array(
      '#type' => 'checkbox',
      '#default_value' => $cross_sync,
      '#title' => t('Use cross conversion between non default currencies.'),
      '#description' => t('If enabled only the rates between the default currency and the other currencies have to be managed. The rates between the other currencies is derived from their rates relative to the default currency.'),
    );

    $form['demo_amount'] = array(
      '#type' => 'textfield',
      '#title' => t('Amount for example conversions:'),
      '#size' => 10,
      '#default_value' => $config->get('demo_amount'),
    );

    $form['synchronize'] = array(
      '#type' => 'checkbox',
      '#title' => t('Synchronize rates on save'),
      '#size' => 10,
      '#default_value' => 0,
    );

    $form['currency'] = [
      '#type' => 'details',
      '#title' => t('Currency exchange rates'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    // Based on cross sync value render form elements.
    switch ($cross_sync) {
      case '1':
        $form['currency'][$currency_default] = [
          '#type' => 'details',
          '#title' => $currency_default,
          '#open' => FALSE,
        ];
        foreach ($active_currencies as $key => $item) {
          if ($key !== $currency_default) {

            $default_rate = isset($config->get('exchange')[$currency_default][$key]['value']) ? $config->get('exchange')[$currency_default][$key]['value'] : 0;
            $default_sync = isset($config->get('exchange')[$currency_default][$key]['sync']) ? $config->get('exchange')[$currency_default][$key]['sync'] : [];

            $form['currency'][$currency_default][$key]['value'] = [
              '#type' => 'textfield',
              '#title' => $key,
              '#description' => t('Exchange rate from @initial to @currency.', [
                '@initial' => $currency_default,
                '@currency' => $item,
              ]),
              '#size' => 20,
              '#default_value' => $default_rate,
              '#disabled' => empty($default_sync[1]) ? TRUE : FALSE,
              '#field_suffix' => t(
                '* @demo_amount @currency_symbol = @amount @conversion_currency_symbol',
                [
                  '@demo_amount' => $config->get('demo_amount'),
                  '@currency_symbol' => $currency_default,
                  '@conversion_currency_symbol' => $key,
                  '@amount' => ($config->get('demo_amount') * $default_rate),
                ]
              ),
            ];

            $form['currency'][$currency_default][$key]['sync'] = [
              '#type' => 'checkboxes',
              '#title' => '',
              '#options' => [1 => 'Manually enter an exchange rate'],
              '#default_value' => $default_sync,
            ];
          }
        }
        break;

      default:
        // Render options in form.
        foreach ($active_currencies as $key => $item) {

          $form['currency'][$key] = [
            '#type' => 'details',
            '#title' => $item,
            '#open' => FALSE,
          ];

          foreach ($active_currencies as $subkey => $subitem) {
            if ($key != $subkey) {

              $default_rate = isset($config->get('exchange')[$key][$subkey]['value']) ? $config->get('exchange')[$key][$subkey]['value'] : 0;
              $default_sync = isset($config->get('exchange')[$key][$subkey]['sync']) ? $config->get('exchange')[$key][$subkey]['sync'] : [];

              $form['currency'][$key][$subkey]['value'] = [
                '#type' => 'textfield',
                '#title' => $subkey,
                '#description' => t('Exchange rate from @initial to @currency.', [
                  '@initial' => $item,
                  '@currency' => $subitem,
                ]),
                '#size' => 20,
                '#default_value' => $default_rate,
                '#disabled' => empty($default_sync[1]) ? TRUE : FALSE,
                '#field_suffix' => t(
                  '* @demo_amount @currency_symbol = @amount @conversion_currency_symbol',
                  [
                    '@demo_amount' => $config->get('demo_amount'),
                    '@currency_symbol' => $key,
                    '@conversion_currency_symbol' => $subkey,
                    '@amount' => ($config->get('demo_amount') * $default_rate),
                  ]
                ),
              ];

              $form['currency'][$key][$subkey]['sync'] = [
                '#type' => 'checkboxes',
                '#title' => '',
                '#options' => [1 => 'Manually enter an exchange rate'],
                '#default_value' => $default_sync,
              ];

            }
          }

        }
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('source') != 'exchange_rate_ecb' && empty($form_state->getValue('api_key'))) {
      $form_state->setErrorByName('api_key', $this->t('API key is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_currency_resolver.currency_conversion');

    // Set values.
    $config->set('source', $form_state->getValue('source'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('cron', (int) $form_state->getValue('cron'))
      ->set('use_cross_sync', $form_state->getValue('use_cross_sync'))
      ->set('demo_amount', $form_state->getValue('demo_amount'))
      ->set('exchange', $form_state->getValue('currency'))
      ->save();

    parent::submitForm($form, $form_state);

    // Synchronize exchange rates after form submit.
    if (!empty($form_state->getValue('synchronize'))) {
      // Start and dispatch event.
      $event = new ExchangeImport($form_state->getValue('source'));
      \Drupal::service('event_dispatcher')->dispatch(CommerceCurrencyResolverEvents::IMPORT, $event);
    }
  }

}
