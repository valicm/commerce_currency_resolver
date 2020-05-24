<?php

namespace Drupal\commerce_currency_resolver\Form;

use Drupal\commerce_currency_resolver\CurrencyHelperInterface;
use Drupal\Component\Datetime\TimeInterface;
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
   * Helper service.
   *
   * @var \Drupal\commerce_currency_resolver\CurrencyHelperInterface
   */
  protected $currencyHelper;

  /**
   * The time object.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack, CurrencyHelperInterface $currency_helper, TimeInterface $time) {
    $this->requestStack = $request_stack;
    $this->currencyHelper = $currency_helper;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('commerce_currency_resolver.currency_helper'),
      $container->get('datetime.time')
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
    $active_currencies = $this->currencyHelper->getCurrencies();

    // Get cookies.
    $cookies = $request->cookies;
    $cookie_name = $this->currencyHelper->getCookieName();

    // Get values from cookie.
    if ($cookies->has($cookie_name) && isset($active_currencies[$cookies->get($cookie_name)])) {
      $selected_currency = $cookies->get($cookie_name);
    }

    else {
      $selected_currency = $this->currencyHelper->defaultCurrencyCode();
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
    setrawcookie($this->currencyHelper->getCookieName(), rawurlencode($selected_currency), $this->time->getRequestTime() + 86400, '/');
  }

}
