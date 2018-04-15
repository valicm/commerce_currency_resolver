<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\CurrencyHelper;

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
      '#title' => $this->t('3rd party exchange service'),
      '#description' => $this->t('Select which external service you want to use for calucalting exchange rates between currencies'),
      '#options' => [
        'manual' => $this->t('Manual'),
        'external' => $this->t('External'),
        'combo' => $this->t('Combination'),
      ],
      '#default_value' => $config->get('currency_conversion'),
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

            $disabled = $config->get('exchange')[$currency_default][$key]['sync'];

            $form['currency'][$currency_default][$key]['value'] = [
              '#type' => 'textfield',
              '#title' => $key,
              '#description' => t('Exchange rate from @initial to @currency.', [
                '@initial' => $currency_default,
                '@currency' => $item,
              ]),
              '#size' => 20,
              '#default_value' => $config->get('exchange')[$currency_default][$key]['value'],
              '#disabled' => empty($disabled[1]) ? TRUE : FALSE,
            ];

            $form['currency'][$currency_default][$key]['sync'] = [
              '#type' => 'checkboxes',
              '#title' => '',
              '#options' => [1 => 'Enter manually exchange rate.'],
              '#default_value' => $config->get('exchange')[$currency_default][$key]['sync'],
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

              $disabled = $config->get('exchange')[$key][$subkey]['sync'];

              $form['currency'][$key][$subkey]['value'] = [
                '#type' => 'textfield',
                '#title' => $subkey,
                '#description' => t('Exchange rate from @initial to @currency.', [
                  '@initial' => $item,
                  '@currency' => $subitem,
                ]),
                '#size' => 20,
                '#default_value' => $config->get('exchange')[$key][$subkey]['value'],
                '#disabled' => empty($disabled[1]) ? TRUE : FALSE,
              ];

              $form['currency'][$key][$subkey]['sync'] = [
                '#type' => 'checkboxes',
                '#title' => '',
                '#options' => [1 => 'Enter manually exchange rate.'],
                '#default_value' => $config->get('exchange')[$key][$subkey]['sync'],
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_currency_resolver.currency_conversion');

    // Set values.
    $config->set('source', $form_state->getValue('source'))
      ->set('use_cross_sync', $form_state->getValue('use_cross_sync'))
      ->set('demo_amount', $form_state->getValue('demo_amount'))
      ->set('exchange', $form_state->getValue('currency'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
