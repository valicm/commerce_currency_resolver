<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\commerce_currency_resolver\CurrencyHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class CommerceCurrencyResolverSelectForm.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceCurrencyResolverSelectForm extends FormBase {

  /**
   * Current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_currency_resolver_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->requestStack->getCurrentRequest();

    // Get all active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();

    // Get cookies.
    $cookies = $request->cookies;

    // Get values from cookie.
    if ($cookies->has('commerce_currency') && isset($active_currencies[$cookies->get('commerce_currency')])) {
      $selected_currency = $cookies->get('commerce_currency');
    }

    else {
      $selected_currency = \Drupal::config('commerce_currency_resolver.settings')->get('currency_default');
    }

    $form['currency'] = [
      '#type' => 'select',
      '#options' => $active_currencies,
      '#default_value' => $selected_currency,
      '#attributes' => [
        'onChange' => ['this.form.submit()'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // Add currency cache context.
    $form['#cache']['contexts'][] = 'currency_resolver';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form value.
    $selected_currency = $form_state->getValue('currency');

    // Set cookie for one day.
    setrawcookie('commerce_currency', rawurlencode($selected_currency), REQUEST_TIME + 86400, '/');
  }

}
